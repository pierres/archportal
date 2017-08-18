<?php

namespace archportal\pages\statistics;

use archportal\lib\Input;
use archportal\lib\Page;
use Doctrine\DBAL\Driver\Connection;
use PDO;
use PDOException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PostPackageList extends Page
{
    /** @var int */
    private $delay = 86400; // 24 hours
    /** @var int */
    private $count = 10;
    /** @var bool */
    private $quiet = false;
    /** @var Connection */
    private $database;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->database = $connection;
    }

    public function prepare(Request $request)
    {
        $this->disallowCaching();
        $this->setContentType('text/plain; charset=UTF-8');

        # Can be rewritten once 2.0 is no longer in use
        $pkgstatsver = $request->request->get('pkgstatsver',
            str_replace('pkgstats/', '', $request->server->get('HTTP_USER_AGENT')));

        if (!in_array($pkgstatsver, array(
            '1.0',
            '2.0',
            '2.1',
            '2.2',
            '2.3',
        ))
        ) {
            throw new BadRequestHttpException('Sorry, your version of pkgstats is not supported.');
        }

        $packages = array_unique(explode("\n", trim($request->request->get('packages'))));
        $packageCount = count($packages);
        if (in_array($pkgstatsver, array('2.2', '2.3'))) {
            $modules = array_unique(explode("\n", trim($request->request->get('modules'))));
            $moduleCount = count($modules);
        } else {
            $modules = array();
            $moduleCount = null;
        }
        $arch = $request->request->get('arch');
        $cpuArch = $request->request->get('cpuarch', '');
        # Can be rewritten once 1.0 is no longer in use
        $mirror = htmlspecialchars($request->request->get('mirror', ''));
        # Can be rewritten once 2.0 is no longer in use
        $this->quiet = ($request->request->get('quiet', 'false') == 'true');

        if (!empty($mirror) && !preg_match('#^(https?|ftp)://\S+/#', $mirror)) {
            $mirror = null;
        } elseif (!empty($mirror) && strlen($mirror) > 255) {
            throw new BadRequestHttpException($mirror . ' is too long.');
        } elseif (empty($mirror)) {
            $mirror = null;
        }
        if (!in_array($arch, array(
            'i686',
            'x86_64',
        ))
        ) {
            throw new BadRequestHttpException(htmlspecialchars($arch) . ' is not a known architecture.');
        }
        if (!in_array($cpuArch, array(
            'i686',
            'x86_64',
            '',
        ))
        ) {
            throw new BadRequestHttpException(htmlspecialchars($cpuArch) . ' is not a known architecture.');
        }
        if ($cpuArch == '') {
            $cpuArch = null;
        }
        if ($packageCount == 0) {
            throw new BadRequestHttpException('Your package list is empty.');
        }
        if ($packageCount > 10000) {
            throw new BadRequestHttpException('So, you have installed more than 10,000 packages?');
        }
        foreach ($packages as $package) {
            if (!preg_match('/^[^-]+\S{0,254}$/', htmlspecialchars($package))) {
                throw new BadRequestHttpException(htmlspecialchars($package) . ' does not look like a valid package');
            }
        }
        if ($moduleCount > 5000) {
            throw new BadRequestHttpException('So, you have loaded more than 5,000 modules?');
        }
        foreach ($modules as $module) {
            if (!preg_match('/^[\w\-]{1,254}$/', $module)) {
                throw new BadRequestHttpException($module . ' does not look like a valid module');
            }
        }
        $this->checkIfAlreadySubmitted($request);
        $countryCode = Input::getClientCountryCode();
        if (empty($countryCode)) {
            $countryCode = null;
        }
        try {
            $this->database->beginTransaction();
            $stm = $this->database->prepare('
            INSERT INTO
                pkgstats_users
            SET
                ip = :ip,
                time = :time,
                arch = :arch,
                cpuarch = :cpuarch,
                countryCode = :countryCode,
                mirror = :mirror,
                packages = :packages,
                modules = :modules
            ');
            $stm->bindValue('ip', sha1($request->getClientIp()), PDO::PARAM_STR);
            $stm->bindValue('time', time(), PDO::PARAM_INT);
            $stm->bindParam('arch', $arch, PDO::PARAM_STR);
            $stm->bindParam('cpuarch', $cpuArch, PDO::PARAM_STR);
            $stm->bindParam('countryCode', $countryCode, PDO::PARAM_STR);
            $stm->bindParam('mirror', $mirror, PDO::PARAM_STR);
            $stm->bindParam('packages', $packageCount, PDO::PARAM_INT);
            $stm->bindParam('modules', $moduleCount, PDO::PARAM_INT);
            $stm->execute();
            $stm = $this->database->prepare('
            INSERT INTO
                pkgstats_packages
            SET
                pkgname = :pkgname,
                month = :month,
                count = 1
            ON DUPLICATE KEY UPDATE
                count = count + 1
            ');
            foreach ($packages as $package) {
                $stm->bindValue('pkgname', htmlspecialchars($package), PDO::PARAM_STR);
                $stm->bindValue('month', date('Ym', time()), PDO::PARAM_INT);
                $stm->execute();
            }
            $stm = $this->database->prepare('
            INSERT INTO
                pkgstats_modules
            SET
                name = :module,
                month = :month,
                count = 1
            ON DUPLICATE KEY UPDATE
                count = count + 1
            ');
            foreach ($modules as $module) {
                $stm->bindParam('module', $module, PDO::PARAM_STR);
                $stm->bindValue('month', date('Ym', time()), PDO::PARAM_INT);
                $stm->execute();
            }
            $this->database->commit();
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw new HttpException(500, $e->getMessage(), $e);
        }
    }

    public function printPage()
    {
        if (!$this->quiet) {
            echo 'Thanks for your submission. :-)' . "\n";
            echo 'See results at ' . $this->createURL('Statistics', array(), true, false) . "\n";
        }
    }

    private function checkIfAlreadySubmitted(Request $request)
    {
        $stm = $this->database->prepare('
        SELECT
            COUNT(*) AS count,
            MIN(time) AS mintime
        FROM
            pkgstats_users
        WHERE
            time >= :time
            AND ip = :ip
        GROUP BY
            ip
        ');
        $stm->bindValue('time', time() - $this->delay, PDO::PARAM_INT);
        $stm->bindValue('ip', sha1($request->getClientIp()), PDO::PARAM_STR);
        $stm->execute();
        $log = $stm->fetch();
        if ($log !== false && $log['count'] >= $this->count) {
            throw new BadRequestHttpException('You already submitted your data ' . $this->count . ' times since ' . $this->l10n->getGmDateTime($log['mintime']) . ' using the IP ' . $request->getClientIp() . ".\n         You are blocked until " . $this->l10n->getGmDateTime($log['mintime'] + $this->delay));
        }
    }
}
