<?php

namespace App\Tests\Twig;

use App\Twig\AppExtension;
use PHPUnit\Framework\TestCase;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtensionTest extends TestCase
{
    public function testFormatBytesIsCallable(): void
    {
        $callable = $this->getFilterCallableFromExtension(new AppExtension(), 'format_bytes');
        if (is_callable($callable)) {
            $result = call_user_func(
                $callable,
                '1'
            );
            $this->assertEquals('1,00 Byte', $result);
        } else {
            $this->fail('Filter has no callable');
        }
    }

    /**
     * @param AbstractExtension $extension
     * @param string $filterName
     * @return callable|null
     */
    private function getFilterCallableFromExtension(AbstractExtension $extension, string $filterName): ?callable
    {
        /** @var TwigFilter $filter */
        foreach ($extension->getFilters() as $filter) {
            if ($filter->getName() == $filterName) {
                return $filter->getCallable();
            }
        }
        throw new \RuntimeException('Filter "' . $filterName . '" was not found.');
    }

    /**
     * @param int $input
     * @param string $output
     * @dataProvider provideByteFormats
     */
    public function testFormatBytes(int $input, string $output): void
    {
        $appExtension = new AppExtension();
        $this->assertEquals($output, $appExtension->formatBytes($input));
    }

    /**
     * @return array<array<int|string>>
     */
    public function provideByteFormats(): array
    {
        return [
            [1, '1,00 Byte'],
            [1024, '1,00 KByte'],
            [1048576, '1,00 MByte'],
            [1073741824, '1,00 GByte'],
            [-1, '-1,00 Byte']
        ];
    }

    public function testUrlPathIsCallable(): void
    {
        $callable = $this->getFilterCallableFromExtension(new AppExtension(), 'url_path');
        if (is_callable($callable)) {
            $result = call_user_func(
                $callable,
                'https://www.archlinux.de/packages'
            );
            $this->assertEquals('/packages', $result);
        } else {
            $this->fail('Filter has no callable');
        }
    }

    public function testUrlPath(): void
    {
        $input = 'https://user:pass@www.archlinux.de:443/path/blah?foo#bar';
        $appExtension = new AppExtension();
        $this->assertEquals('/path/blah', $appExtension->urlPath($input));
    }

    public function testUrlHostIsCallable(): void
    {
        $callable = $this->getFilterCallableFromExtension(new AppExtension(), 'url_host');
        if (is_callable($callable)) {
            $result = call_user_func(
                $callable,
                'https://www.archlinux.de/packages'
            );
            $this->assertEquals('www.archlinux.de', $result);
        } else {
            $this->fail('Filter has no callable');
        }
    }

    public function testUrlHost(): void
    {
        $input = 'https://user:pass@www.archlinux.de:443/path/blah?foo#bar';
        $appExtension = new AppExtension();
        $this->assertEquals('www.archlinux.de', $appExtension->urlHost($input));
    }
}
