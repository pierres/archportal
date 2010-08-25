<?php
/*
	Copyright 2002-2010 Pierre Schmitz <pierre@archlinux.de>

	This file is part of archlinux.de.

	archlinux.de is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	archlinux.de is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with archlinux.de.  If not, see <http://www.gnu.org/licenses/>.
*/

class PackageDetails extends Page{

private $pkgid = 0;
private $repo = '';
private $arch = '';
private $pkgname = '';


public function prepare()
	{
	$this->setValue('title', 'Paket-Details');

	try
		{
		$this->repo = $this->Input->Get->getString('repo');
		$this->arch = $this->Input->Get->getString('arch');
		$this->pkgname = $this->Input->Get->getString('pkgname');
		}
	catch (RequestException $e)
		{
		$this->showFailure('Kein Paket angegeben!');
		}

	try
		{
		$stm = $this->DB->prepare
			('
			SELECT
				packages.id,
				packages.filename,
				packages.name,
				packages.base,
				packages.version,
				packages.desc,
				packages.csize,
				packages.isize,
				packages.md5sum,
				packages.url,
				packages.builddate,
				packages.mtime,
				architectures.name AS architecture,
				repositories.name AS repository,
				architectures.id AS architectureid,
				repositories.id AS repositoryid,
				packagers.name AS packager,
				packagers.id AS packagerid,
				packagers.email AS packageremail
			FROM
				packages
					LEFT JOIN packagers ON packages.packager = packagers.id,
				architectures,
				repositories
			WHERE
				repositories.name = ?
				AND architectures.name = ?
				AND packages.name = ?
				AND packages.arch = architectures.id
				AND packages.repository = repositories.id
			');
		$stm->bindString($this->repo);
		$stm->bindString($this->arch);
		$stm->bindString($this->pkgname);
		$data = $stm->getRow();
		$stm->close();
		}
	catch (DBNoDataException $e)
		{
		$stm->close();
		$this->Output->setStatus(Output::NOT_FOUND);
		$this->showFailure('Paket nicht gefunden!');
		}

	$this->pkgid = $data['id'];
	$this->setValue('title', $data['name']);

	$body = '<div class="box">
		<h2>'.$data['name'].'</h2>
		<table id="packagedetails">
			<tr>
				<th colspan="2" class="packagedetailshead">Programm-Details</th>
			</tr>
			<tr>
				<th>Name</th>
				<td>'.$data['name'].'</td>
			</tr>
			<tr>
				<th>Version</th>
				<td>'.$data['version'].'</td>
			</tr>
			<tr>
				<th>Beschreibung</th>
				<td>'.$data['desc'].'</td>
			</tr>
			<tr>
				<th>URL</th>
				<td><a rel="nofollow" href="'.$data['url'].'">'.$data['url'].'</a></td>
			</tr>
			<tr>
				<th>Lizenzen</th>
				<td>'.$this->getLicenses().'</td>
			</tr>
			<tr>
				<th colspan="2" class="packagedetailshead">Paket-Details</th>
			</tr>
			<tr>
				<th>Repositorium</th>
				<td><a href="?page=Packages;repository='.$data['repositoryid'].'">'.$data['repository'].'</a></td>
			</tr>
			<tr>
				<th>Architektur</th>
				<td><a href="?page=Packages;architecture='.$data['architectureid'].'">'.$data['architecture'].'</a></td>
			</tr>
			<tr>
				<th>Gruppen</th>
				<td>'.$this->getGroups().'</td>
			</tr>
			<tr>
				<th>Packer</th>
				<td><a href="?page=Packages;packager='.$data['packagerid'].'">'.$data['packager'].'</a>'.(!empty($data['packageremail']) ? ' <a rel="nofollow" href="mailto:'.$data['packageremail'].'">@</a>' : '').'</td>
			</tr>
			<tr>
				<th>Aktualisierung</th>
				<td>'.$this->L10n->getDateTime($data['builddate']).'</td>
			</tr>
			<tr>
				<th>Veröffentlichung</th>
				<td>'.$this->L10n->getDateTime($data['mtime']).'</td>
			</tr>
			<tr>
				<th>Quellen</th>
				<td><a href="http://repos.archlinux.org/wsvn/'.(in_array($data['repository'], array('community', 'community-testing', 'multilib')) ? 'community' : 'packages').'/'.$data['base'].'/">Versions-Verwaltung</a></td>
			</tr>
			<tr>
				<th>Fehler</th>
				<td><a href="http://bugs.archlinux.org/index.php?string=%5B'.$data['name'].'%5D">Bug Tracker</a></td>
			</tr>
			<tr>
				<th>Paket</th>
				<td><a href="?page=GetFileFromMirror;file='.$data['repository'].'/os/'.$data['architecture'].'/'.$data['filename'].'">'.$data['filename'].'</a></td>
			</tr>
			<tr>
				<th>MD5-Prüfsumme</th>
				<td><code>'.$data['md5sum'].'</code></td>
			</tr>
			<tr>
				<th>Paket-Größe</th>
				<td>'.$this->formatBytes($data['csize']).'Byte</td>
			</tr>
			<tr>
				<th>Installations-Größe</th>
				<td>'.$this->formatBytes($data['isize']).'Byte</td>
			</tr>
		</table>
		<table id="packagedependencies">
			<tr>
				<th colspan="5" class="packagedependencieshead">Abhängigkeiten</th>
			</tr>
			<tr>
				<th>hängt ab von</th>
				<th>wird benötigt von</th>
				<th>stellt bereit</th>
				<th>kollidiert mit</th>
				<th>ersetzt</th>
			</tr>
			<tr>
				<td>
					'.$this->getDependencies().'
				</td>
				<td>
					'.$this->getInverseDependencies().'
				</td>
				<td>
					'.$this->getProvides().'
				</td>
				<td>
					'.$this->getConflicts().'
				</td>
				<td>
					'.$this->getReplaces().'
				</td>
			</tr>
			<tr>
				<th>hängt optional ab von</th>
				<th>wird optional benötigt von</th>
				<th colspan="3">&nbsp;</th>
			</tr>
			<tr>
				<td>
					'.$this->getOptionalDependencies().'
				</td>
				<td>
					'.$this->getInverseOptionalDependencies().'
				</td>
				<td colspan="3">&nbsp;</td>
			</tr>
		</table>
		<table id="packagedependencies">
			<tr>
				<th class="packagedependencieshead">Dateien</th>
			</tr>
			<tr>
				<td>
					'.($this->Input->Get->isInt('showfiles') ? $this->getFiles() : '<a style="font-size:10px;margin:10px;" href="?page=PackageDetails;repo='.$this->repo.';arch='.$this->arch.';pkgname='.$this->pkgname.';showfiles=1">Dateien anzeigen</a>').'
				</td>
			</tr>
		</table>
		</div>
		';

	$this->setValue('body', $body);
	}

private function formatBytes($bytes)
	{
	$kb = 1024;
	$mb = $kb * 1024;
	$gb = $mb * 1024;

	if ($bytes >= $gb)	// GB
		{
		return round($bytes / $gb, 2).' G';
		}
	elseif ($bytes >= $mb)	// MB
		{
		return round($bytes / $mb, 2).' M';
		}
	elseif ($bytes >= $kb)	// KB
		{
		return round($bytes / $kb, 2).' K';
		}
	else			//  B
		{
		return $bytes.' ';
		}
	}

private function getLicenses()
	{
	try
		{
		$stm = $this->DB->prepare
			('
			SELECT
				licenses.name
			FROM
				licenses,
				package_license
			WHERE
				package_license.license = licenses.id
				AND package_license.package = ?
			');
		$stm->bindInteger($this->pkgid);

		foreach ($stm->getColumnSet() as $license)
			{
			$list[] = $license;
			}
		$stm->close();
		}
	catch (DBNoDataException $e)
		{
		$stm->close();
		$list = array();
		}

	return implode(', ', $list);
	}

private function getGroups()
	{
	try
		{
		$stm = $this->DB->prepare
			('
			SELECT
				groups.id,
				groups.name
			FROM
				groups,
				package_group
			WHERE
				package_group.group = groups.id
				AND package_group.package = ?
			');
		$stm->bindInteger($this->pkgid);

		foreach ($stm->getRowSet() as $group)
			{
			$list[] = '<a href="?page=Packages;group='.$group['id'].'">'.$group['name'].'</a>';
			}
		$stm->close();
		}
	catch (DBNoDataException $e)
		{
		$stm->close();
		$list = array();
		}

	return implode(', ', $list);
	}

private function getFiles()
	{
	try
		{
		$stm = $this->DB->prepare
			('
			SELECT
				path
			FROM
				files
			WHERE
				package = ?
			');
		$stm->bindInteger($this->pkgid);

		$list = '<ul>';
		foreach ($stm->getColumnSet() as $file)
			{
			$list .= '<li>'.$file.'</li>';
			}
		$list .= '</ul>';
		$stm->close();
		}
	catch (DBNoDataException $e)
		{
		$stm->close();
		$list = '';
		}

	return $list;
	}

private function getDependencies()
	{
	try
		{
		$stm = $this->DB->prepare
			('
			SELECT
				packages.id,
				packages.name,
				depends.comment,
				architectures.name AS arch,
				repositories.name AS repo
			FROM
				depends
					LEFT JOIN packages
					ON depends.depends = packages.id,
				architectures,
				repositories
			WHERE
				depends.package = ?
				AND packages.arch = architectures.id
				AND packages.repository = repositories.id
			ORDER BY
				packages.name
			');
		$stm->bindInteger($this->pkgid);

		$list = '<ul>';
		foreach ($stm->getRowSet() as $dependency)
			{
			$list .= '<li><a href="?page=PackageDetails;repo='.$dependency['repo'].';arch='.$dependency['arch'].';pkgname='.$dependency['name'].'">'.$dependency['name'].'</a>'.$dependency['comment'].'</li>';
			}
		$list .= '</ul>';
		$stm->close();
		}
	catch (DBNoDataException $e)
		{
		$stm->close();
		$list = '';
		}

	return $list;
	}

private function getInverseDependencies()
	{
	try
		{
		$stm = $this->DB->prepare
			('
			SELECT
				packages.id,
				packages.name,
				depends.comment,
				architectures.name AS arch,
				repositories.name AS repo
			FROM
				packages,
				depends,
				architectures,
				repositories
			WHERE
				depends.depends = ?
				AND depends.package = packages.id
				AND packages.arch = architectures.id
				AND packages.repository = repositories.id
			ORDER BY
				packages.name
			');
		$stm->bindInteger($this->pkgid);

		$list = '<ul>';
		foreach ($stm->getRowSet() as $dependency)
			{
			$list .= '<li><a href="?page=PackageDetails;repo='.$dependency['repo'].';arch='.$dependency['arch'].';pkgname='.$dependency['name'].'">'.$dependency['name'].'</a>'.$dependency['comment'].'</li>';
			}
		$list .= '</ul>';
		$stm->close();
		}
	catch (DBNoDataException $e)
		{
		$stm->close();
		$list = '';
		}

	return $list;
	}

private function getOptionalDependencies()
	{
	try
		{
		$stm = $this->DB->prepare
			('
			SELECT
				packages.id,
				packages.name,
				optdepends.comment,
				architectures.name AS arch,
				repositories.name AS repo
			FROM
				optdepends
					LEFT JOIN packages
					ON optdepends.optdepends = packages.id,
				architectures,
				repositories
			WHERE
				optdepends.package = ?
				AND packages.arch = architectures.id
				AND packages.repository = repositories.id
			ORDER BY
				packages.name
			');
		$stm->bindInteger($this->pkgid);

		$list = '<ul>';
		foreach ($stm->getRowSet() as $optdependency)
			{
			$list .= '<li><a href="?page=PackageDetails;repo='.$optdependency['repo'].';arch='.$optdependency['arch'].';pkgname='.$optdependency['name'].'">'.$optdependency['name'].'</a>&nbsp;'.cutString($optdependency['comment'], 30).'</li>';
			}
		$list .= '</ul>';
		$stm->close();
		}
	catch (DBNoDataException $e)
		{
		$stm->close();
		$list = '';
		}

	return $list;
	}

private function getInverseOptionalDependencies()
	{
	try
		{
		$stm = $this->DB->prepare
			('
			SELECT
				packages.id,
				packages.name,
				optdepends.comment,
				architectures.name AS arch,
				repositories.name AS repo
			FROM
				packages,
				optdepends,
				architectures,
				repositories
			WHERE
				optdepends.optdepends = ?
				AND optdepends.package = packages.id
				AND packages.arch = architectures.id
				AND packages.repository = repositories.id
			ORDER BY
				packages.name
			');
		$stm->bindInteger($this->pkgid);

		$list = '<ul>';
		foreach ($stm->getRowSet() as $optdependency)
			{
			$list .= '<li><a href="?page=PackageDetails;repo='.$optdependency['repo'].';arch='.$optdependency['arch'].';pkgname='.$optdependency['name'].'">'.$optdependency['name'].'</a>&nbsp;'.cutString($optdependency['comment'], 30).'</li>';
			}
		$list .= '</ul>';
		$stm->close();
		}
	catch (DBNoDataException $e)
		{
		$stm->close();
		$list = '';
		}

	return $list;
	}

private function getProvides()
	{
	try
		{
		$stm = $this->DB->prepare
			('
			SELECT
				packages.id,
				packages.name,
				provides.comment,
				architectures.name AS arch,
				repositories.name AS repo
			FROM
				provides
					LEFT JOIN packages
					ON provides.provides = packages.id,
				architectures,
				repositories
			WHERE
				provides.package = ?
				AND packages.arch = architectures.id
				AND packages.repository = repositories.id
			ORDER BY
				packages.name
			');
		$stm->bindInteger($this->pkgid);

		$list = '<ul>';
		foreach ($stm->getRowSet() as $dependency)
			{
			$list .= '<li><a href="?page=PackageDetails;repo='.$dependency['repo'].';arch='.$dependency['arch'].';pkgname='.$dependency['name'].'">'.$dependency['name'].'</a>'.$dependency['comment'].'</li>';
			}
		$list .= '</ul>';
		$stm->close();
		}
	catch (DBNoDataException $e)
		{
		$stm->close();
		$list = '';
		}

	return $list;
	}

private function getConflicts()
	{
	try
		{
		$stm = $this->DB->prepare
			('
			SELECT
				packages.id,
				packages.name,
				conflicts.comment,
				architectures.name AS arch,
				repositories.name AS repo
			FROM
				conflicts
					LEFT JOIN packages
					ON conflicts.conflicts = packages.id,
				architectures,
				repositories
			WHERE
				conflicts.package = ?
				AND packages.arch = architectures.id
				AND packages.repository = repositories.id
			ORDER BY
				packages.name
			');
		$stm->bindInteger($this->pkgid);

		$list = '<ul>';
		foreach ($stm->getRowSet() as $dependency)
			{
			$list .= '<li><a href="?page=PackageDetails;repo='.$dependency['repo'].';arch='.$dependency['arch'].';pkgname='.$dependency['name'].'">'.$dependency['name'].'</a>'.$dependency['comment'].'</li>';
			}
		$list .= '</ul>';
		$stm->close();
		}
	catch (DBNoDataException $e)
		{
		$stm->close();
		$list = '';
		}

	return $list;
	}

private function getReplaces()
	{
	try
		{
		$stm = $this->DB->prepare
			('
			SELECT
				packages.id,
				packages.name,
				replaces.comment,
				architectures.name AS arch,
				repositories.name AS repo
			FROM
				replaces
					LEFT JOIN packages
					ON replaces.replaces = packages.id,
				architectures,
				repositories
			WHERE
				replaces.package = ?
				AND packages.arch = architectures.id
				AND packages.repository = repositories.id
			ORDER BY
				packages.name
			');
		$stm->bindInteger($this->pkgid);

		$list = '<ul>';
		foreach ($stm->getRowSet() as $dependency)
			{
			$list .= '<li><a href="?page=PackageDetails;repo='.$dependency['repo'].';arch='.$dependency['arch'].';pkgname='.$dependency['name'].'">'.$dependency['name'].'</a>'.$dependency['comment'].'</li>';
			}
		$list .= '</ul>';
		$stm->close();
		}
	catch (DBNoDataException $e)
		{
		$stm->close();
		$list = '';
		}

	return $list;
	}

}

?>
