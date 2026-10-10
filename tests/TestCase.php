<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', config('app.url'));
    }

    protected function pngUpload(string $name = 'photo.png', int $width = 400, int $height = 500): UploadedFile
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $pixels = str_repeat("\x00".str_repeat("\xD9\x8C\xA6", $width), $height);

        return UploadedFile::fake()->createWithContent(
            $name,
            "\x89PNG\r\n\x1a\n"
                .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
                .$chunk('IDAT', gzcompress($pixels))
                .$chunk('IEND', ''),
        );
    }
}
