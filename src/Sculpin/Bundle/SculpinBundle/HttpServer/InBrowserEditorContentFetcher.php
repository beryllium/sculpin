<?php

declare(strict_types=1);

namespace Sculpin\Bundle\SculpinBundle\HttpServer;

use League\MimeTypeDetection\MimeTypeDetector;
use Sculpin\Core\Source\SourceSet;
use Symfony\Component\Finder\Finder;

class InBrowserEditorContentFetcher implements ContentFetcher
{
    protected array $pathMap;
    protected array $sourceMap;
    protected string $docroot;
    protected string $sourceDir;

    public function __construct(
        SourceSet $set,
        string $docroot,
        string $sourceDir,
        protected readonly MimeTypeDetector $detector,
    ) {
        $this->docroot   = rtrim($docroot, '/') . '/';
        $this->sourceDir = rtrim($sourceDir, '/') . '/';

        $this->buildPathMap($set);
        $this->buildSourceMap();
    }

    public function buildPathMap(SourceSet $set): void
    {
        $pathMap = [];
        $docRoot = rtrim($this->docroot, '/\\');
        $sources = $set->allSources();

        foreach ($sources as $source) {
            $relativePath      = ltrim($source->permalink()->relativeFilePath(), '/\\');
            $pathKey           = $docRoot . DIRECTORY_SEPARATOR . $relativePath;
            $pathMap[$pathKey] = $source->file()->getPathname();
        }

        $this->pathMap = $pathMap;
    }

    public function buildSourceMap() {
        $files = Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->ignoreDotFiles(false)
            ->followLinks()
            ->in($this->sourceDir);

        $this->sourceMap = [];

        foreach ($files as $file) {
            $this->sourceMap[$file->getRelativePathname()] = [
                'pathname' => $file->getRelativePathname(),
                'file' => $file->getFilename(),
                'type' => $file->getType(),
                'mime' => $this->detector->detectMimeTypeFromFile($file->getPathname()),
                'ext' => mb_strtolower($file->getExtension()),
            ];
        }
    }

    public function fetchData(string $path): ?string
    {
        $body = file_get_contents($path);

        return $body ? $this->process($path, $body) : null;
    }

    protected function process(string $path, string $body): string
    {
        // if we don't know the disk location for edits, exit early
        if (!isset($this->pathMap[$path])) {
            return $body;
        }

        // if body content doesn't end with </html>, exit early
        if (false === $htmlEndPos = stripos(substr($body, -20), '</html>')) {
            return $body;
        }

        $url      = str_replace($this->docroot, '', $path);
        $diskPath = str_replace($this->sourceDir, '', $this->pathMap[$path]);
        $content  = file_get_contents($this->pathMap[$path]);

        $json = json_encode([
            'url'      => $url,
            'diskPath' => $diskPath,
            'content'  => $content,
            'contentHash' => md5_file($path),
        ]);

        // modify the body content to activate the live editor
        return $body . <<<EOF
        <script>
          var SCULPIN_EDITOR_METADATA = {$json};
        </script>
        <script src="/_SCULPIN_/editor.js" type="text/javascript"></script>
        EOF;
    }

    public function editorJs(): string
    {
        return file_get_contents(__DIR__ . '/../Resources/js/editor.js') ?: '';
    }

    public function diskPathExists(string $path): bool
    {
        $fullPath = $this->docroot . $path;

        if (!isset($this->pathMap[$fullPath])) {
            return false;
        }

        return file_exists($this->pathMap[$fullPath]);
    }

    public function save(string $path, string $content): void
    {
        if (!$this->diskPathExists($path)) {
            return;
        }

        file_put_contents($this->pathMap[$this->docroot . $path], $content);
    }

    public function hash(string $path): ?string
    {
        if (!$this->diskPathExists($path)) {
            return null;
        }

        return md5_file($this->docroot . $path) ?: null;
    }

    public function editorCss(): string
    {
        return file_get_contents(__DIR__ . '/../Resources/css/editor.css') ?: '';
    }

    public function getMetadata(string $path = '', string $source = ''): array
    {
        $url = $path ? $this->pathMap[$path] ?? $source : $source;

        // Normalize $url by erasing the sourceDir, if present
        $diskPath = str_replace($this->sourceDir, '', $url);
        $fullDiskPath = $this->sourceDir . $diskPath;

        $content = file_exists($fullDiskPath) ? file_get_contents($fullDiskPath) : null;

        // @todo Calculate the rendered view path, if possible
        $renderedView = $this->docroot . $path;

        $output = [
            'url' => $path,
            'pathMap' => $this->pathMap,
            'sourceMap' => $this->sourceMap, // @todo see if this can include the generated file path
        ];

        if ($content) {
            $output['diskPath'] = $diskPath;
            $output['content'] = $content;
            $output['contentHashSource'] = md5_file($fullDiskPath);

            // renderedView does not map 1:1 to all files. For example, layouts map to all files that use the layout.
            // this makes it difficult to decide which file should be the source of truth for whether an edit has been applied.
            // Punting on this for now, and only providing generated file hash for 1:1 mappings.
            $output['contentHashGenerated'] = ($path && file_exists($renderedView)) ? md5_file($renderedView) : 'unknown';
        }

        return $output;
    }
}
