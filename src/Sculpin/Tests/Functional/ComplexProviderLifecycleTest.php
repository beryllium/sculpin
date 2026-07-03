<?php

namespace Functional;

use Sculpin\Tests\Functional\FunctionalTestCase;

class ComplexProviderLifecycleTest extends FunctionalTestCase
{
    protected const string PROJECT_DIR = '/__ComplexProviderLifecycleFixture__';

    #[\Override]
    protected function setUp(): void
    {
        $outputDir = $this->projectDir() . '/output_test';
        if (self::$fs->exists($outputDir)) {
            self::$fs->remove($outputDir);
        }
    }

    public function testComplexLifecycleBuildsProperly(): void
    {
        $expectedFile = 'index.html';
        $expectedFileContents = 'hypothetical';

        $this->assertProjectLacksFile('/output_test/' . $expectedFile);

        $this->executeSculpin(['generate']);

        $this->assertProjectHasGeneratedFile('/' . $expectedFile);
        $this->assertGeneratedFileHasContent('/' . $expectedFile, $expectedFileContents);
    }
}
