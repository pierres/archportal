<?php

namespace App\Tests\ArchLinux;

use App\ArchLinux\PackageDatabaseMirror;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PackageDatabaseMirrorTest extends TestCase
{
    public function testGetMirrorUrl()
    {
        /** @var HttpClientInterface|MockObject $httpClient */
        $httpClient = $this->createMock(HttpClientInterface::class);

        /** @var CacheItemPoolInterface|MockObject $cache */
        $cache = $this->createMock(CacheItemPoolInterface::class);

        $packageDatabaseMirror = new PackageDatabaseMirror($httpClient, $cache, 'foo');

        $this->assertEquals('foo', $packageDatabaseMirror->getMirrorUrl());
    }

    public function testHasUpdatedIsTrueForNewMirror()
    {
        /** @var HttpClientInterface|MockObject $httpClient */
        $httpClient = $this->createMock(HttpClientInterface::class);

        $cache = new ArrayAdapter();

        $packageDatabaseMirror = new PackageDatabaseMirror($httpClient, $cache, 'foo');
        $this->assertTrue($packageDatabaseMirror->hasUpdated());
    }

    /**
     * @param int $oldLastUpdated
     * @param int $newLastUpdated
     * @dataProvider provideLastUpdated
     */
    public function testHasUpdated(int $oldLastUpdated, int $newLastUpdated)
    {
        $httpClient = new MockHttpClient(new MockResponse((string)$newLastUpdated));

        /** @var CacheItemInterface|MockObject $cacheItem */
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(true);
        $cacheItem
            ->expects($this->once())
            ->method('get')
            ->willReturn($oldLastUpdated);

        /** @var CacheItemPoolInterface|MockObject $cache */
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache
            ->expects($this->once())
            ->method('getItem')
            ->with('UpdatePackages-lastupdate')
            ->willReturn($cacheItem);

        $packageDatabaseMirror = new PackageDatabaseMirror($httpClient, $cache, 'http://foo');
        $this->assertEquals($oldLastUpdated != $newLastUpdated, $packageDatabaseMirror->hasUpdated());
    }

    public function testUpdateLastUpdate()
    {
        /** @var HttpClientInterface|MockObject $httpClient */
        $httpClient = $this->createMock(HttpClientInterface::class);

        $cache = new ArrayAdapter();

        $packageDatabaseMirror = new PackageDatabaseMirror($httpClient, $cache, '');
        $packageDatabaseMirror->updateLastUpdate();

        $this->assertEquals(0, $cache->getItem('UpdatePackages-lastupdate')->get());
    }

    /**
     * @return array
     */
    public function provideLastUpdated(): array
    {
        return [
            [0, 1],
            [1, 0],
            [0, 0]
        ];
    }
}
