<?php

namespace archportal\pages;

use archportal\lib\Config;
use archportal\lib\Input;
use archportal\lib\Output;
use Doctrine\DBAL\Driver\Connection;
use PDO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetFileFromMirror extends Output
{
    /** @var int */
    private $lastsync = 0;
    /** @var string */
    private $file = '';
    /** @var Connection */
    private $database;
    /** @var string */
    private $targetUrl;
    /** @var string */
    private $clientId;

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
        $this->file = $request->get('file', '');
        if (!preg_match('#^[a-zA-Z0-9\.\-\+_/:]{1,255}$#', $this->file)) {
            throw new BadRequestHttpException('Invalid file name');
        }
        if (strpos($this->file, '/') === 0) {
            $this->file = substr($this->file, 1);
        }
        $repositories = implode('|', array_keys(Config::get('packages', 'repositories')));
        $architectures = implode('|', $this->getAvailableArchitectures());
        $pkgextension = '(?:'.$architectures.'|any).pkg.tar.(?:g|x)z';
        if (preg_match('#^('.$repositories.')/os/('.$architectures.')/([^-]+.*)-[^-]+-[^-]+-'.$pkgextension.'$#',
            $this->file, $matches)) {
            $pkgdate = $this->database->prepare('
                SELECT
                    packages.mtime
                FROM
                    packages
                    LEFT JOIN repositories
                    ON packages.repository = repositories.id
                    LEFT JOIN architectures
                    ON repositories.arch = architectures.id
                WHERE
                    packages.name = :pkgname
                    AND repositories.name = :repository
                    AND architectures.name = :architecture
                ');
            $pkgdate->bindParam('pkgname', $matches[3], PDO::PARAM_STR);
            $pkgdate->bindParam('repository', $matches[1], PDO::PARAM_STR);
            $pkgdate->bindParam('architecture', $matches[2], PDO::PARAM_STR);
            $pkgdate->execute();
            if ($pkgdate->rowCount() == 0) {
                throw new NotFoundHttpException('Package was not found');
            }
            $this->lastsync = $pkgdate->fetchColumn();
        } elseif (preg_match('#^iso/([0-9]{4}\.[0-9]{2}\.[0-9]{2})/#', $this->file, $matches)) {
            $releasedate = $this->database->prepare('
                SELECT
                    created
                FROM
                    releng_releases
                WHERE
                    version = :version
                    AND available = 1
                ');
            $releasedate->bindParam('version', $matches[1], PDO::PARAM_STR);
            $releasedate->execute();
            if ($releasedate->rowCount() == 0) {
                throw new NotFoundHttpException('ISO image was not found');
            }
            $this->lastsync = $releasedate->fetchColumn();
        } else {
            $this->lastsync = time() - (60 * 60 * 24);
        }

        $this->clientId = crc32($request->getClientIp());
        $this->targetUrl = $this->getMirror($this->lastsync).$this->file;
    }

    public function printPage()
    {
        $this->redirectToUrl($this->targetUrl);
    }

    /**
     * @return array
     */
    private function getAvailableArchitectures(): array
    {
        $uniqueArchitectures = array();
        foreach (Config::get('packages', 'repositories') as $architectures) {
            foreach ($architectures as $architecture) {
                $uniqueArchitectures[$architecture] = 1;
            }
        }

        return array_keys($uniqueArchitectures);
    }

    /**
     * @param int $lastsync
     *
     * @return string
     */
    private function getMirror(int $lastsync): string
    {
        $countryCode = Input::getClientCountryCode();
        if (empty($countryCode)) {
            $countryCode = Config::get('mirrors', 'country');
        }
        $stm = $this->database->prepare('
            SELECT
                url
            FROM
                mirrors
            WHERE
                lastsync > :lastsync
                AND countryCode = :countryCode
                AND protocol IN ("http", "htttps")
            ORDER BY RAND(:clientId) LIMIT 1
            ');
        $stm->bindParam('lastsync', $lastsync, PDO::PARAM_INT);
        $stm->bindParam('countryCode', $countryCode, PDO::PARAM_STR);
        $stm->bindParam('clientId', $this->clientId, PDO::PARAM_INT);
        $stm->execute();
        if ($stm->rowCount() == 0) {
            // Let's see if any mirror is recent enough
            $stm = $this->database->prepare('
                SELECT
                    url
                FROM
                    mirrors
                WHERE
                    lastsync > :lastsync
                    AND protocol IN ("http", "htttps")
                ORDER BY RAND(:clientId) LIMIT 1
                ');
            $stm->bindParam('lastsync', $lastsync, PDO::PARAM_INT);
            $stm->bindParam('clientId', $this->clientId, PDO::PARAM_INT);
            $stm->execute();
            if ($stm->rowCount() == 0) {
                throw new NotFoundHttpException('File was not found');
            }
        }

        return $stm->fetchColumn();
    }
}
