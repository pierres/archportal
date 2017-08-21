<?php

namespace AppBundle\Controller;

use archportal\lib\GeoIP;
use Doctrine\DBAL\Driver\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class MirrorController extends Controller
{
    /** @var Connection */
    private $database;
    /** @var GeoIP */
    private $geoIP;

    /**
     * @param Connection $connection
     * @param GeoIP $geoIP
     */
    public function __construct(Connection $connection, GeoIP $geoIP)
    {
        $this->database = $connection;
        $this->geoIP = $geoIP;
    }

    /**
     * @Route("/download/{file}", requirements={"file": "^[a-zA-Z0-9\.\-\+_/:]{1,255}$"}, methods={"GET"})
     * @param string $file
     * @param Request $request
     * @return Response
     */
    public function indexAction(string $file, Request $request): Response
    {
        $repositories = implode('|', array_keys($this->getParameter('app.packages.repositories')));
        $architectures = implode('|', $this->getAvailableArchitectures());
        $pkgextension = '(?:' . $architectures . '|any).pkg.tar.(?:g|x)z';
        if (preg_match(
            '#^(' . $repositories . ')/os/(' . $architectures . ')/([^-]+.*)-[^-]+-[^-]+-' . $pkgextension . '$#',
            $file,
            $matches
        )) {
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
            $pkgdate->bindParam('pkgname', $matches[3], \PDO::PARAM_STR);
            $pkgdate->bindParam('repository', $matches[1], \PDO::PARAM_STR);
            $pkgdate->bindParam('architecture', $matches[2], \PDO::PARAM_STR);
            $pkgdate->execute();
            if ($pkgdate->rowCount() == 0) {
                throw new NotFoundHttpException('Package was not found');
            }
            $lastsync = $pkgdate->fetchColumn();
        } elseif (preg_match('#^iso/([0-9]{4}\.[0-9]{2}\.[0-9]{2})/#', $file, $matches)) {
            $releasedate = $this->database->prepare('
                SELECT
                    created
                FROM
                    releng_releases
                WHERE
                    version = :version
                    AND available = 1
                ');
            $releasedate->bindParam('version', $matches[1], \PDO::PARAM_STR);
            $releasedate->execute();
            if ($releasedate->rowCount() == 0) {
                throw new NotFoundHttpException('ISO image was not found');
            }
            $lastsync = $releasedate->fetchColumn();
        } else {
            $lastsync = time() - (60 * 60 * 24);
        }

        $targetUrl = $this->getMirror($lastsync, $request->getClientIp()) . $file;

        return $this->redirect($targetUrl);
    }

    /**
     * @return array
     */
    private function getAvailableArchitectures(): array
    {
        $uniqueArchitectures = array();
        foreach ($this->getParameter('app.packages.repositories') as $architectures) {
            foreach ($architectures as $architecture) {
                $uniqueArchitectures[$architecture] = 1;
            }
        }

        return array_keys($uniqueArchitectures);
    }

    /**
     * @param int $lastsync
     *
     * @param string $clientIp
     * @return string
     */
    private function getMirror(int $lastsync, string $clientIp): string
    {
        $clientId = crc32($clientIp);

        $countryCode = $this->geoIP->getClientCountryCode($clientIp);
        if (empty($countryCode)) {
            $countryCode = $this->getParameter('app.mirrors.country');
        }
        $stm = $this->database->prepare('
            SELECT
                url
            FROM
                mirrors
            WHERE
                lastsync > :lastsync
                AND countryCode = :countryCode
                AND protocol = "https"
            ORDER BY RAND(:clientId) LIMIT 1
            ');
        $stm->bindParam('lastsync', $lastsync, \PDO::PARAM_INT);
        $stm->bindParam('countryCode', $countryCode, \PDO::PARAM_STR);
        $stm->bindParam('clientId', $clientId, \PDO::PARAM_INT);
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
                    AND protocol = "https"
                ORDER BY RAND(:clientId) LIMIT 1
                ');
            $stm->bindParam('lastsync', $lastsync, \PDO::PARAM_INT);
            $stm->bindParam('clientId', $clientId, \PDO::PARAM_INT);
            $stm->execute();
            if ($stm->rowCount() == 0) {
                throw new NotFoundHttpException('File was not found');
            }
        }

        return $stm->fetchColumn();
    }
}