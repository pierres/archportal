#!/usr/bin/php
<?php

/*
	Copyright 2002-2007 Pierre Schmitz <pschmitz@laber-land.de>

	This file is part of LL.

	LL is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	LL is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with LL.  If not, see <http://www.gnu.org/licenses/>.
*/

ini_set('max_execution_time', 0);
define('IN_LL', null);

require ('../LLPath.php');
ini_set('include_path', ini_get('include_path').':'.LL_PATH.':../');

require ('modules/Functions.php');
require ('modules/Modul.php');
require ('modules/Settings.php');
require ('modules/Exceptions.php');
require ('modules/IDBCachable.php');
require ('pages/abstract/Page.php');
require ('pages/GetFileFromMirror.php');
require ('pages/MirrorStatus.php');

class CheckMirrors extends Modul {


public function __construct()
	{
	self::__set('Settings', new Settings());
	self::__set('DB', new DB(
		$this->Settings->getValue('sql_user'),
		$this->Settings->getValue('sql_password'),
		$this->Settings->getValue('sql_database')
		));
	}

private function getTmpDir()
	{
	$tmp = ini_get('upload_tmp_dir');
	return empty($tmp) ? '/tmp' : $tmp;
	}

private function getLockFile()
	{
	return $this->getTmpDir().'/MirrorCheckRunning.lock';
	}

public function runUpdate()
	{
	if (file_exists($this->getLockFile()))
		{
		die('MirrorCheck still in progress');
		}
	else
		{
		touch($this->getLockFile());
		chmod($this->getLockFile(), 0600);
		}

	$this->removeOldEntries();

	try
		{
		$mirrors = $this->DB->getRowSet
			('
			SELECT
				host,
				ftp,
				http,
				i686,
				x86_64
			FROM
				mirrors
			WHERE
				official = 1
				AND deleted = 0
				AND (LENGTH(ftp) > 0 OR LENGTH(http) > 0)
				AND (i686 = 1 OR x86_64 = 1)
			')->toArray();
		}
	catch (DBNoDataException $e)
		{
		$mirrors = array();
		}

		$this->curlHandles = array();

	foreach ($mirrors as $mirror)
		{
		$arch = $mirror['i686'] > 0 ? 'i686' : 'x86_64';
		$repo = 'core';

		if (strlen($mirror['ftp']) > 0)
			{
			$url = 'ftp://'.$mirror['ftp'];
			}
		else
			{
			$url = 'http://'.$mirror['http'];
			}

		try
			{
			$result = $this->getLastsyncFromMirror($url.'/'.$repo.'/os/'.$arch);
			$this->insertLogEntry($mirror['host'], $result['lastsync'], $result['totaltime']);
			}
		catch (RuntimeException $e)
			{
			$this->insertErrorEntry($mirror['host'], $e->getMessage());
			}
		}

	GetFileFromMirror::updateDBCache();

	foreach ($this->Settings->getValue('locales') as $locale)
		{
		$this->L10n->setLocale($locale);
		MirrorStatus::updateDBCache();
		}

	unlink($this->getLockFile());
	}

private function getLastsyncFromMirror($url)
	{
	if (false === ($curl = curl_init($url.'/lastsync')))
		{
		throw new RuntimeException('faild to init curl: '.htmlspecialchars($url));
		}

	curl_setopt($curl, CURLOPT_FAILONERROR, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_MAXREDIRS, 1);
	curl_setopt($curl, CURLOPT_TIMEOUT, 120);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($curl, CURLOPT_ENCODING, '');
	curl_setopt($curl, CURLOPT_USERPWD, 'anonymous:bob@archlinux.de');
	curl_setopt($curl, CURLOPT_FTP_USE_EPSV, false);

	$content = curl_exec($curl);

	if (false === $content)
		{
		throw new RuntimeException(htmlspecialchars(curl_error($curl)), curl_errno($curl));
		}

	$totaltime = curl_getinfo($curl, CURLINFO_TOTAL_TIME);

	curl_close($curl);

	$lastsync = intval(trim($content));

	if (0 == $lastsync)
		{
		throw new RuntimeException('invalid lastsync time', 1);
		}

	return array('lastsync' => $lastsync, 'totaltime' => $totaltime);
	}

private function insertLogEntry($host, $lastsync, $totaltime)
	{
	$stm = $this->DB->prepare
		('
		INSERT INTO
			mirror_log
		SET
			host = ?,
			time = ?,
			lastsync = ?,
			totaltime = ?
		');
	$stm->bindString($host);
	$stm->bindInteger(time());
	$stm->bindInteger($lastsync);
	$stm->bindDouble($totaltime);
	$stm->execute();
	$stm->close();
	}

private function insertErrorEntry($host, $error)
	{
	$stm = $this->DB->prepare
		('
		INSERT INTO
			mirror_log
		SET
			host = ?,
			time = ?,
			error = ?
		');
	$stm->bindString($host);
	$stm->bindInteger(time());
	$stm->bindString($error);
	$stm->execute();
	$stm->close();
	}

private function removeOldEntries()
	{
	$stm = $this->DB->prepare
		('
		DELETE FROM
			mirror_log
		WHERE
			time < ?
		');
	$stm->bindInteger(time() - 60*60*24*30*6);
	$stm->execute();
	$stm->close();

	$this->DB->execute
		('
		DELETE FROM
			mirror_log
		WHERE
			host NOT IN (SELECT host FROM pkgdb.mirrors WHERE official = 1 AND deleted = 0)
		');
	}

}

$upd = new CheckMirrors();
$upd->runUpdate();

?>