<?php

declare(strict_types=1);

namespace MezzioTest\Tooling\CreateHandler;

use Mezzio\Tooling\CreateHandler\CreateHandler;
use Mezzio\Tooling\CreateHandler\CreateHandlerException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function file_put_contents;
use function json_encode;

class CreateHandlerTest extends TestCase
{
    /** @var vfsStreamDirectory */
    private $dir;

    /** @var string */
    private $projectRoot;

    protected function setUp(): void
    {
        $this->dir         = vfsStream::setup('project');
        $this->projectRoot = vfsStream::url('project');
    }

    public function testProcessRaisesExceptionWhenComposerJsonNotPresentInProjectRoot(): void
    {
        $generator = new CreateHandler(CreateHandler::CLASS_SKELETON, $this->projectRoot);

        $this->expectException(CreateHandlerException::class);
        $this->expectExceptionMessage('find a composer.json');

        $generator->process('Foo\Bar\BazHandler');
    }

    public function testProcessRaisesExceptionForMalformedComposerJson(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', 'not-a-value');
        $generator = new CreateHandler(CreateHandler::CLASS_SKELETON, $this->projectRoot);

        $this->expectException(CreateHandlerException::class);
        $this->expectExceptionMessage('Unable to parse');

        $generator->process('Foo\Bar\BazHandler');
    }

    public function testProcessRaisesExceptionIfComposerJsonDoesNotDefinePsr4Autoloaders(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', json_encode(['name' => 'some/project']));
        $generator = new CreateHandler(CreateHandler::CLASS_SKELETON, $this->projectRoot);

        $this->expectException(CreateHandlerException::class);
        $this->expectExceptionMessage('PSR-4 autoloaders');

        $generator->process('Foo\Bar\BazHandler');
    }

    public function testProcessRaisesExceptionIfComposerJsonDefinesMalformedPsr4Autoloaders(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', json_encode([
            'autoload' => [
                'psr-4' => 'not-valid',
            ],
        ]));
        $generator = new CreateHandler(CreateHandler::CLASS_SKELETON, $this->projectRoot);

        $this->expectException(CreateHandlerException::class);
        $this->expectExceptionMessage('PSR-4 autoloaders');

        $generator->process('Foo\Bar\BazHandler');
    }

    public function testProcessRaisesExceptionIfClassDoesNotMatchAnyAutoloadableNamespaces(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', json_encode([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/App/',
                ],
            ],
        ]));
        $generator = new CreateHandler(CreateHandler::CLASS_SKELETON, $this->projectRoot);

        $this->expectException(CreateHandlerException::class);
        $this->expectExceptionMessage('Unable to match');

        $generator->process('Foo\Bar\BazHandler');
    }

    public function testProcessRaisesExceptionIfUnableToCreateSubPath(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', json_encode([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/App/',
                    'Foo\\' => 'src/Foo/src/',
                ],
            ],
        ]));
        vfsStream::newDirectory('src/Foo/src', 0555)->at($this->dir);

        $generator = new CreateHandler(CreateHandler::CLASS_SKELETON, $this->projectRoot);

        $this->expectException(CreateHandlerException::class);
        $this->expectExceptionMessage('Unable to create the directory');

        $generator->process('Foo\Bar\BazHandler');
    }

    public function testProcessCanCreateHandlerInNamespaceRoot(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', json_encode([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/App/',
                    'Foo\\' => 'src/Foo/',
                ],
            ],
        ]));
        vfsStream::newDirectory('src/Foo/src', 0775)->at($this->dir);

        $generator = new CreateHandler(CreateHandler::CLASS_SKELETON, $this->projectRoot);

        $expectedPath = vfsStream::url('project/src/Foo/BarHandler.php');
        self::assertEquals(
            $expectedPath,
            $generator->process('Foo\BarHandler')
        );

        $classFileContents = file_get_contents($expectedPath);
        self::assertMatchesRegularExpression('#^\<\?php#s', $classFileContents);
        self::assertMatchesRegularExpression('#^namespace Foo;$#m', $classFileContents);
        self::assertMatchesRegularExpression(
            '#^class BarHandler implements RequestHandlerInterface$#m',
            $classFileContents
        );
        self::assertMatchesRegularExpression(
            '#^\s{4}public function handle\(ServerRequestInterface \$request\) : ResponseInterface$#m',
            $classFileContents
        );
    }

    public function testProcessCanCreateHandlerInSubNamespacePath(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', json_encode([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/App/',
                    'Foo\\' => 'src/Foo/',
                ],
            ],
        ]));
        vfsStream::newDirectory('src/Foo/src', 0775)->at($this->dir);

        $generator = new CreateHandler(CreateHandler::CLASS_SKELETON, $this->projectRoot);

        $expectedPath = vfsStream::url('project/src/Foo/Bar/BazHandler.php');
        self::assertEquals(
            $expectedPath,
            $generator->process('Foo\Bar\BazHandler')
        );

        $classFileContents = file_get_contents($expectedPath);
        self::assertMatchesRegularExpression('#^\<\?php#s', $classFileContents);
        self::assertMatchesRegularExpression('#^namespace Foo\\\\Bar;$#m', $classFileContents);
        self::assertMatchesRegularExpression(
            '#^class BazHandler implements RequestHandlerInterface$#m',
            $classFileContents
        );
        self::assertMatchesRegularExpression(
            '#^\s{4}public function handle\(ServerRequestInterface \$request\) : ResponseInterface$#m',
            $classFileContents
        );
    }

    public function testProcessCanCreateHandlerInModuleNamespaceRoot(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', json_encode([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/App/',
                    'Foo\\' => 'src/Foo/src/',
                ],
            ],
        ]));
        vfsStream::newDirectory('src/Foo/src', 0775)->at($this->dir);

        $generator = new CreateHandler(CreateHandler::CLASS_SKELETON, $this->projectRoot);

        $expectedPath = vfsStream::url('project/src/Foo/src/BarHandler.php');
        self::assertEquals(
            $expectedPath,
            $generator->process('Foo\BarHandler')
        );

        $classFileContents = file_get_contents($expectedPath);
        self::assertMatchesRegularExpression('#^\<\?php#s', $classFileContents);
        self::assertMatchesRegularExpression('#^namespace Foo;$#m', $classFileContents);
        self::assertMatchesRegularExpression(
            '#^class BarHandler implements RequestHandlerInterface$#m',
            $classFileContents
        );
        self::assertMatchesRegularExpression(
            '#^\s{4}public function handle\(ServerRequestInterface \$request\) : ResponseInterface$#m',
            $classFileContents
        );
    }

    public function testProcessCanCreateHandlerInModuleSubNamespacePath(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', json_encode([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/App/',
                    'Foo\\' => 'src/Foo/src/',
                ],
            ],
        ]));
        vfsStream::newDirectory('src/Foo/src', 0775)->at($this->dir);

        $generator = new CreateHandler(CreateHandler::CLASS_SKELETON, $this->projectRoot);

        $expectedPath = vfsStream::url('project/src/Foo/src/Bar/BazHandler.php');
        self::assertEquals(
            $expectedPath,
            $generator->process('Foo\Bar\BazHandler')
        );

        $classFileContents = file_get_contents($expectedPath);
        self::assertMatchesRegularExpression('#^\<\?php#s', $classFileContents);
        self::assertMatchesRegularExpression('#^namespace Foo\\\\Bar;$#m', $classFileContents);
        self::assertMatchesRegularExpression(
            '#^class BazHandler implements RequestHandlerInterface$#m',
            $classFileContents
        );
        self::assertMatchesRegularExpression(
            '#^\s{4}public function handle\(ServerRequestInterface \$request\) : ResponseInterface$#m',
            $classFileContents
        );
    }

    public function testProcessThrowsExceptionIfClassAlreadyExists(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', json_encode([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/App/',
                ],
            ],
        ]));

        vfsStream::newDirectory('src/App/Foo', 0775)->at($this->dir);
        file_put_contents($this->projectRoot . '/src/App/Foo/BarHandler.php', 'App\Foo\BarHandler');

        $generator = new CreateHandler(CreateHandler::CLASS_SKELETON, $this->projectRoot);

        $this->expectException(CreateHandlerException::class);
        $this->expectExceptionMessage('Class BarHandler already exists');

        $generator->process('App\Foo\BarHandler');
    }

    public function testTheClassSkeletonParameterOverridesTheConstant(): void
    {
        file_put_contents($this->projectRoot . '/composer.json', json_encode([
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/App/',
                    'Foo\\' => 'src/Foo/',
                ],
            ],
        ]));
        vfsStream::newDirectory('src/Foo/src', 0775)->at($this->dir);

        $generator = new CreateHandler('class Foo\Bar\BazHandler', $this->projectRoot);

        $expectedPath = vfsStream::url('project/src/Foo/Bar/BazHandler.php');
        self::assertEquals(
            $expectedPath,
            $generator->process('Foo\Bar\BazHandler')
        );

        $classFileContents = file_get_contents($expectedPath);
        self::assertStringContainsString('class Foo\Bar\BazHandler', $classFileContents);
    }
}
