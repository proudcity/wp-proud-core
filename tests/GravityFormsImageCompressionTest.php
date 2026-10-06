<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the image-compression helpers added to
 * plugin_override/gravityforms/proud-gravityforms.php for issue #2912.
 *
 * Covers the acceptance-criteria table in
 * "Github Issue Notes/2912 - Compress Gravity Forms Image Uploads.md", minus
 * the pixel-cap and stateless-mode-branch rows, which Curtis's 2026-09-24
 * decisions removed from scope entirely (no pixel cap/filter; a true
 * `stateless` value is just a GCS URL, already rejected by the local-path
 * helper like any other non-local URL).
 *
 * Every function under test lives at the top level of proud-gravityforms.php,
 * OUTSIDE if (class_exists('GFCommon')), so it loads and runs without a real
 * Gravity Forms/WordPress install. wp_get_image_editor() is stubbed to return
 * a WP_Error by default (tests/gravityforms-stubs.php); each test that needs
 * a working editor supplies its own Mockery double whose save() writes a real
 * file, so the size-comparison/rename/cleanup logic runs against real bytes
 * on disk rather than a fully mocked filesystem.
 */
class GravityFormsImageCompressionTest extends TestCase
{
    private string $tmpDir;
    private string $uploadRoot;
    private string $uploadUrlRoot = 'https://example.com/wp-content/uploads/gravity_forms/';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->tmpDir     = sys_get_temp_dir() . '/proud-gform-test-' . uniqid('', true);
        $this->uploadRoot = $this->tmpDir . '/uploads';
        mkdir($this->uploadRoot, 0777, true);

        GFFormsModel::$upload_root      = $this->uploadRoot . '/';
        GFFormsModel::$upload_url_root  = $this->uploadUrlRoot;
        GFFormsModel::$permission_calls = [];
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        $this->removeDirectory($this->tmpDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir) && ! is_link($dir)) {
            return;
        }

        if (is_link($dir)) {
            unlink($dir);
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_link($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    // -------------------------------------------------------------------------
    // Fixture builders
    // -------------------------------------------------------------------------

    private function makeJpeg(string $path, int $width, int $height, int $quality = 95): string
    {
        $img = imagecreatetruecolor($width, $height);
        // Random noise rather than a flat fill: a flat-colour JPEG compresses
        // to a few hundred bytes regardless of dimensions, which would make
        // the "smaller output" comparisons meaningless.
        for ($x = 0; $x < $width; $x += 4) {
            for ($y = 0; $y < $height; $y += 4) {
                imagefilledrectangle($img, $x, $y, $x + 3, $y + 3, imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }
        imagejpeg($img, $path, $quality);
        imagedestroy($img);

        return $path;
    }

    private function makePng(string $path, int $width, int $height): string
    {
        $img = imagecreatetruecolor($width, $height);
        for ($x = 0; $x < $width; $x += 4) {
            for ($y = 0; $y < $height; $y += 4) {
                imagefilledrectangle($img, $x, $y, $x + 3, $y + 3, imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    private function makeWebp(string $path, int $width, int $height): string
    {
        $img = imagecreatetruecolor($width, $height);
        for ($x = 0; $x < $width; $x += 4) {
            for ($y = 0; $y < $height; $y += 4) {
                imagefilledrectangle($img, $x, $y, $x + 3, $y + 3, imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }
        imagewebp($img, $path);
        imagedestroy($img);

        return $path;
    }

    private function makeAnimatedWebpBytes(string $path): string
    {
        // Not a fully valid animated WebP -- proud_gform_compress_image_file()
        // only ever gets this far after getimagesize() already confirmed
        // image/webp, so the detector only needs the ANIM chunk marker GD's
        // real animated encoder would also emit within the first bytes.
        file_put_contents($path, "RIFF\x00\x00\x00\x00WEBPVP8X\x00\x00\x00\x00ANIM\x00\x00\x00\x00");

        return $path;
    }

    /**
     * A structurally valid PNG signature + IHDR chunk (all getimagesize()
     * needs for width/height/mime), followed by acTL/IDAT markers so
     * proud_gform_is_animated_image() sees the APNG signal. Not a fully valid
     * APNG -- the CRCs and IDAT payload are not real -- but the compress
     * pipeline never gets past the animated check to notice.
     */
    private function makeAnimatedPngBytes(string $path, int $width = 40, int $height = 40): string
    {
        $ihdrData = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);
        $ihdr     = pack('N', strlen($ihdrData)) . 'IHDR' . $ihdrData . pack('N', 0);
        $acTL     = pack('N', 8) . 'acTL' . str_repeat("\x00", 8) . pack('N', 0);
        $idat     = pack('N', 4) . 'IDAT' . str_repeat("\x00", 4) . pack('N', 0);

        file_put_contents($path, "\x89PNG\r\n\x1a\n" . $ihdr . $acTL . $idat);

        return $path;
    }

    private function makePdf(string $path): string
    {
        file_put_contents($path, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");

        return $path;
    }

    /**
     * A real PNG, saved with a .jpg extension -- getimagesize() succeeds with
     * mime image/png, but the extension does not match. Distinct from a
     * disguised PDF: this exercises the "extension AND getimagesize(), never
     * client MIME" rule specifically.
     */
    private function makeMismatchedExtension(string $path): string
    {
        return $this->makePng($path, 40, 40);
    }

    /**
     * Copies fixtures into GFFormsModel::$upload_root so
     * proud_gform_local_upload_path()/GFFormsModel calls resolve against a
     * real, confined directory tree, and returns [local path, public url].
     */
    private function placeInUploadRoot(string $sourcePath, string $relative): array
    {
        $target = $this->uploadRoot . '/' . $relative;
        @mkdir(dirname($target), 0777, true);
        rename($sourcePath, $target);

        return [$target, $this->uploadUrlRoot . $relative];
    }

    private function mockField(string $type = 'fileupload', mixed $compress = null): object
    {
        $field = new class {
            public $type;
            public $proudCompressImages;
        };
        $field->type = $type;
        if (null !== $compress) {
            $field->proudCompressImages = $compress;
        }

        return $field;
    }

    /**
     * Editor double. save() writes a real (tiny, guaranteed-smaller-than-any-
     * fixture) file by default so the temp-file/rename/size-comparison logic
     * in proud_gform_compress_image_file() runs against real bytes on disk.
     */
    private function mockEditor(array $overrides = [])
    {
        $editor = Mockery::mock();
        $editor->shouldReceive('maybe_exif_rotate')
            ->zeroOrMoreTimes()
            ->andReturn($overrides['exif_rotate'] ?? true);
        $editor->shouldReceive('set_quality')
            ->zeroOrMoreTimes()
            ->andReturn(true);
        $editor->shouldReceive('resize')
            ->zeroOrMoreTimes()
            ->andReturn($overrides['resize'] ?? true);
        $editor->shouldReceive('save')
            ->zeroOrMoreTimes()
            ->andReturnUsing($overrides['save'] ?? static function ($destination, $mime) {
                file_put_contents($destination, 'x');
                return ['path' => $destination, 'file' => basename($destination), 'mime-type' => $mime];
            });

        return $editor;
    }

    // -------------------------------------------------------------------------
    // proud_gform_compress_enabled()
    // -------------------------------------------------------------------------

    public function test_compress_enabled_default_true_when_unset(): void
    {
        $field = $this->mockField();
        $this->assertTrue(\Proud\Gform\proud_gform_compress_enabled($field));
    }

    public function test_compress_enabled_true_when_explicitly_true(): void
    {
        $field = $this->mockField('fileupload', true);
        $this->assertTrue(\Proud\Gform\proud_gform_compress_enabled($field));
    }

    #[DataProvider('disabledValues')]
    public function test_compress_enabled_false_for_disabled_values($value): void
    {
        $field = $this->mockField('fileupload', $value);
        $this->assertFalse(\Proud\Gform\proud_gform_compress_enabled($field));
    }

    public static function disabledValues(): array
    {
        return [
            'bool false'   => [false],
            'string false' => ['false'],
            'zero'         => [0],
            'string zero'  => ['0'],
        ];
    }

    /**
     * The regression this helper exists to prevent: GF_Field implements
     * ArrayAccess and offsetGet() returns '' for a missing key rather than
     * null, which filter_var() would coerce to false -- silently disabling
     * compression on every field that has never touched the new setting.
     * Object access must be used, never array access.
     */
    public function test_compress_enabled_true_for_field_missing_property_even_if_array_access_would_lie(): void
    {
        $field = new class implements ArrayAccess {
            public $type = 'fileupload';

            public function offsetExists($offset): bool
            {
                return true;
            }
            public function offsetGet($offset): mixed
            {
                // Mirrors GF_Field::offsetGet() for an undeclared key.
                return '';
            }
            public function offsetSet($offset, $value): void
            {
            }
            public function offsetUnset($offset): void
            {
            }
        };

        $this->assertTrue(\Proud\Gform\proud_gform_compress_enabled($field));
    }

    // -------------------------------------------------------------------------
    // proud_gform_upload_urls()
    // -------------------------------------------------------------------------

    public function test_upload_urls_plain_url_string(): void
    {
        $urls = \Proud\Gform\proud_gform_upload_urls('https://example.com/wp-content/uploads/gravity_forms/1-abc/photo.jpg');
        $this->assertSame(['https://example.com/wp-content/uploads/gravity_forms/1-abc/photo.jpg'], $urls);
    }

    public function test_upload_urls_json_array_multi_file(): void
    {
        $value = json_encode(['https://example.com/a.jpg', 'https://example.com/b.jpg']);
        $this->assertSame(
            ['https://example.com/a.jpg', 'https://example.com/b.jpg'],
            \Proud\Gform\proud_gform_upload_urls($value)
        );
    }

    /**
     * GF 2.10+ storageType=json single-file field: a JSON array of one URL,
     * not a plain string. Must be parsed the same as multi-file (finding A).
     */
    public function test_upload_urls_json_single_file(): void
    {
        $value = json_encode(['https://example.com/one.jpg']);
        $this->assertSame(['https://example.com/one.jpg'], \Proud\Gform\proud_gform_upload_urls($value));
    }

    /**
     * get_prepared_input_value()/create_lead() shape: JSON array of arrays
     * (tmp_path/tmp_url), not strings. Must be ignored, not touched.
     */
    public function test_upload_urls_tmp_array_shape_ignored(): void
    {
        $value = json_encode([
            ['tmp_name' => 'tmp/abc', 'uploaded_filename' => 'photo.jpg'],
        ]);
        $this->assertSame([], \Proud\Gform\proud_gform_upload_urls($value));
    }

    public function test_upload_urls_empty_value_returns_empty_array(): void
    {
        $this->assertSame([], \Proud\Gform\proud_gform_upload_urls(''));
        $this->assertSame([], \Proud\Gform\proud_gform_upload_urls(null));
    }

    // -------------------------------------------------------------------------
    // proud_gform_local_upload_path()
    // -------------------------------------------------------------------------

    public function test_local_upload_path_resolves_url_within_root(): void
    {
        $file = $this->makeJpeg($this->tmpDir . '/src.jpg', 10, 10);
        [$target, $url] = $this->placeInUploadRoot($file, '1-abc/photo.jpg');

        $resolved = \Proud\Gform\proud_gform_local_upload_path($url, $this->uploadUrlRoot, $this->uploadRoot . '/');

        $this->assertSame(realpath($target), $resolved);
    }

    public function test_local_upload_path_rejects_gcs_url(): void
    {
        $resolved = \Proud\Gform\proud_gform_local_upload_path(
            'https://storage.googleapis.com/proudcity/somesite/uploads/gravity_forms/1-abc/photo.jpg',
            $this->uploadUrlRoot,
            $this->uploadRoot . '/'
        );

        $this->assertNull($resolved);
    }

    public function test_local_upload_path_rejects_dot_dot_traversal(): void
    {
        $resolved = \Proud\Gform\proud_gform_local_upload_path(
            $this->uploadUrlRoot . '1-abc/../../../../etc/passwd',
            $this->uploadUrlRoot,
            $this->uploadRoot . '/'
        );

        $this->assertNull($resolved);
    }

    public function test_local_upload_path_rejects_tmp_dir(): void
    {
        mkdir($this->uploadRoot . '/1-abc/tmp', 0777, true);
        file_put_contents($this->uploadRoot . '/1-abc/tmp/photo.jpg', 'x');

        $resolved = \Proud\Gform\proud_gform_local_upload_path(
            $this->uploadUrlRoot . '1-abc/tmp/photo.jpg',
            $this->uploadUrlRoot,
            $this->uploadRoot . '/'
        );

        $this->assertNull($resolved);
    }

    public function test_local_upload_path_rejects_direct_symlink(): void
    {
        $real = $this->makeJpeg($this->tmpDir . '/outside.jpg', 10, 10);
        mkdir($this->uploadRoot . '/1-abc', 0777, true);
        symlink($real, $this->uploadRoot . '/1-abc/photo.jpg');

        $resolved = \Proud\Gform\proud_gform_local_upload_path(
            $this->uploadUrlRoot . '1-abc/photo.jpg',
            $this->uploadUrlRoot,
            $this->uploadRoot . '/'
        );

        $this->assertNull($resolved);
    }

    public function test_local_upload_path_rejects_path_escaping_root_via_symlinked_directory(): void
    {
        $outside = $this->tmpDir . '/outside';
        mkdir($outside, 0777, true);
        $this->makeJpeg($outside . '/photo.jpg', 10, 10);

        // 1-abc is a symlinked directory pointing outside the upload root; the
        // file itself isn't a symlink, but its real path escapes root.
        symlink($outside, $this->uploadRoot . '/1-abc');

        $resolved = \Proud\Gform\proud_gform_local_upload_path(
            $this->uploadUrlRoot . '1-abc/photo.jpg',
            $this->uploadUrlRoot,
            $this->uploadRoot . '/'
        );

        $this->assertNull($resolved);
    }

    // -------------------------------------------------------------------------
    // proud_gform_image_fits_memory()
    // -------------------------------------------------------------------------

    public function test_image_fits_memory_true_when_unlimited(): void
    {
        Functions\when('wp_convert_hr_to_bytes')->justReturn(-1);

        $this->assertTrue(\Proud\Gform\proud_gform_image_fits_memory(4000, 3000, 2000));
    }

    public function test_image_fits_memory_true_when_plenty_available(): void
    {
        Functions\when('wp_convert_hr_to_bytes')->justReturn(1024 * 1024 * 1024);

        $this->assertTrue(\Proud\Gform\proud_gform_image_fits_memory(4000, 3000, 2000));
    }

    public function test_image_fits_memory_false_when_insufficient(): void
    {
        Functions\when('wp_convert_hr_to_bytes')->justReturn(1000);

        $this->assertFalse(\Proud\Gform\proud_gform_image_fits_memory(20000, 15000, 2000));
    }

    // -------------------------------------------------------------------------
    // proud_gform_compress_image_file() -- acceptance row 1 (resize + skip small)
    // -------------------------------------------------------------------------

    public function test_compress_image_file_resizes_large_jpeg(): void
    {
        $path = $this->makeJpeg($this->uploadRoot . '/photo.jpg', 400, 300, 95);
        Functions\when('wp_get_image_editor')->justReturn($this->mockEditor());

        $status = \Proud\Gform\proud_gform_compress_image_file($path, 200, 82);

        $this->assertSame('resized', $status);
        $this->assertFileExists($path);
        $this->assertSame(1, filesize($path), 'The mocked editor output must have replaced the original file.');
    }

    public function test_compress_image_file_skips_already_small_image_without_calling_editor(): void
    {
        $path = $this->makeJpeg($this->uploadRoot . '/photo.jpg', 100, 80, 95);
        $originalSize = filesize($path);

        Functions\expect('wp_get_image_editor')->never();

        $status = \Proud\Gform\proud_gform_compress_image_file($path, 2000, 82);

        $this->assertSame('skipped_small', $status);
        $this->assertSame($originalSize, filesize($path));
    }

    public function test_compress_image_file_calls_maybe_exif_rotate_before_resize(): void
    {
        $path = $this->makeJpeg($this->uploadRoot . '/photo.jpg', 400, 300, 95);

        $calls  = [];
        $editor = Mockery::mock();
        $editor->shouldReceive('maybe_exif_rotate')->once()->andReturnUsing(function () use (&$calls) {
            $calls[] = 'exif';
            return true;
        });
        $editor->shouldReceive('set_quality')->zeroOrMoreTimes()->andReturn(true);
        $editor->shouldReceive('resize')->once()->andReturnUsing(function () use (&$calls) {
            $calls[] = 'resize';
            return true;
        });
        $editor->shouldReceive('save')->once()->andReturnUsing(static function ($destination, $mime) {
            file_put_contents($destination, 'x');
            return ['path' => $destination];
        });

        Functions\when('wp_get_image_editor')->justReturn($editor);

        \Proud\Gform\proud_gform_compress_image_file($path, 200, 82);

        $this->assertSame(['exif', 'resize'], $calls);
    }

    // -------------------------------------------------------------------------
    // proud_gform_compress_image_file() -- acceptance row 4 (non-images)
    // -------------------------------------------------------------------------

    public function test_compress_image_file_skips_pdf(): void
    {
        $path = $this->makePdf($this->uploadRoot . '/doc.pdf');
        $status = \Proud\Gform\proud_gform_compress_image_file($path, 2000, 82);
        $this->assertSame('skipped_not_image', $status);
    }

    public function test_compress_image_file_skips_pdf_disguised_as_jpg(): void
    {
        $path = $this->makePdf($this->uploadRoot . '/doc.jpg');
        $status = \Proud\Gform\proud_gform_compress_image_file($path, 2000, 82);
        $this->assertSame('skipped_not_image', $status);
    }

    public function test_compress_image_file_skips_extension_mime_mismatch(): void
    {
        // Real PNG bytes saved with a .jpg extension.
        $path = $this->makeMismatchedExtension($this->uploadRoot . '/photo.jpg');
        $status = \Proud\Gform\proud_gform_compress_image_file($path, 2000, 82);
        $this->assertSame('skipped_not_image', $status);
    }

    // -------------------------------------------------------------------------
    // Animated images out of scope
    // -------------------------------------------------------------------------

    /**
     * proud_gform_is_animated_image() is a pure byte-sniffing helper, tested
     * directly rather than through the full pipeline: hand-crafting a fully
     * valid animated WebP (VP8X + ANIM + ANMF frames) just to satisfy
     * getimagesize() would test GD's WebP parser, not our detector.
     */
    public function test_is_animated_image_detects_webp_anim_chunk(): void
    {
        $path = $this->makeAnimatedWebpBytes($this->tmpDir . '/anim.webp');
        $this->assertTrue(\Proud\Gform\proud_gform_is_animated_image($path, 'image/webp'));
    }

    public function test_is_animated_image_false_for_static_webp(): void
    {
        $path = $this->makeWebp($this->tmpDir . '/static.webp', 40, 40);
        $this->assertFalse(\Proud\Gform\proud_gform_is_animated_image($path, 'image/webp'));
    }

    public function test_is_animated_image_false_for_static_png(): void
    {
        $path = $this->makePng($this->tmpDir . '/static.png', 40, 40);
        $this->assertFalse(\Proud\Gform\proud_gform_is_animated_image($path, 'image/png'));
    }

    /**
     * Through the full pipeline: a PNG with a valid IHDR (so getimagesize()
     * succeeds) plus an acTL chunk ahead of IDAT (the APNG signal) must be
     * skipped rather than resized.
     */
    public function test_compress_image_file_skips_animated_png(): void
    {
        $path = $this->makeAnimatedPngBytes($this->uploadRoot . '/anim.png', 4000, 3000);

        Functions\expect('wp_get_image_editor')->never();

        $status = \Proud\Gform\proud_gform_compress_image_file($path, 2000, 82);

        $this->assertSame('skipped_animated', $status);
    }

    // -------------------------------------------------------------------------
    // proud_gform_compress_image_file() -- acceptance row 6 (failures keep original)
    // -------------------------------------------------------------------------

    public function test_compress_image_file_keeps_original_when_editor_is_wp_error(): void
    {
        $path = $this->makeJpeg($this->uploadRoot . '/photo.jpg', 400, 300);
        $original = file_get_contents($path);

        Functions\when('wp_get_image_editor')->justReturn(new WP_Error('no_editor', 'nope'));

        $status = \Proud\Gform\proud_gform_compress_image_file($path, 200, 82);

        $this->assertSame('failed_editor', $status);
        $this->assertSame($original, file_get_contents($path));
    }

    public function test_compress_image_file_keeps_original_when_resize_is_wp_error(): void
    {
        $path = $this->makeJpeg($this->uploadRoot . '/photo.jpg', 400, 300);
        $original = file_get_contents($path);

        Functions\when('wp_get_image_editor')->justReturn($this->mockEditor(['resize' => new WP_Error('resize_failed', 'nope')]));

        $status = \Proud\Gform\proud_gform_compress_image_file($path, 200, 82);

        $this->assertSame('failed_resize', $status);
        $this->assertSame($original, file_get_contents($path));
    }

    public function test_compress_image_file_keeps_original_and_cleans_temp_when_save_is_wp_error(): void
    {
        $path = $this->makeJpeg($this->uploadRoot . '/photo.jpg', 400, 300);
        $original = file_get_contents($path);

        Functions\when('wp_get_image_editor')->justReturn($this->mockEditor([
            'save' => static fn () => new WP_Error('save_failed', 'nope'),
        ]));

        $status = \Proud\Gform\proud_gform_compress_image_file($path, 200, 82);

        $this->assertSame('failed_save', $status);
        $this->assertSame($original, file_get_contents($path));
        $this->assertCount(1, glob($this->uploadRoot . '/*'), 'No temp file should remain next to the original.');
    }

    public function test_compress_image_file_keeps_original_and_cleans_temp_when_save_remaps_path(): void
    {
        $path = $this->makeJpeg($this->uploadRoot . '/photo.jpg', 400, 300);
        $original = file_get_contents($path);

        Functions\when('wp_get_image_editor')->justReturn($this->mockEditor([
            'save' => function ($destination, $mime) {
                $remapped = $destination . '.png';
                file_put_contents($remapped, 'x');
                return ['path' => $remapped];
            },
        ]));

        $status = \Proud\Gform\proud_gform_compress_image_file($path, 200, 82);

        $this->assertSame('failed_output_path', $status);
        $this->assertSame($original, file_get_contents($path));
        $this->assertCount(1, glob($this->uploadRoot . '/*'), 'Both the temp file and the remapped file must be cleaned up.');
    }

    public function test_compress_image_file_keeps_original_when_output_is_not_smaller(): void
    {
        $path = $this->makeJpeg($this->uploadRoot . '/photo.jpg', 400, 300, 10);
        $original = file_get_contents($path);
        $originalSize = strlen($original);

        Functions\when('wp_get_image_editor')->justReturn($this->mockEditor([
            'save' => function ($destination, $mime) use ($originalSize) {
                file_put_contents($destination, str_repeat('x', $originalSize + 1000));
                return ['path' => $destination];
            },
        ]));

        $status = \Proud\Gform\proud_gform_compress_image_file($path, 200, 82);

        $this->assertSame('skipped_not_smaller', $status);
        $this->assertSame($original, file_get_contents($path));
        $this->assertCount(1, glob($this->uploadRoot . '/*'), 'The larger temp output must not be left behind.');
    }

    public function test_compress_image_file_keeps_original_when_rename_fails(): void
    {
        $path = $this->makeJpeg($this->uploadRoot . '/photo.jpg', 400, 300);
        $original = file_get_contents($path);

        Functions\when('wp_get_image_editor')->justReturn($this->mockEditor());
        Functions\when('rename')->justReturn(false);

        $status = \Proud\Gform\proud_gform_compress_image_file($path, 200, 82);

        $this->assertSame('failed_rename', $status);
        $this->assertSame($original, file_get_contents($path));
    }

    public function test_compress_image_file_skips_when_memory_guard_fails(): void
    {
        $path = $this->makeJpeg($this->uploadRoot . '/photo.jpg', 400, 300);
        $original = file_get_contents($path);

        Functions\when('wp_convert_hr_to_bytes')->justReturn(1);
        Functions\expect('wp_get_image_editor')->never();

        $status = \Proud\Gform\proud_gform_compress_image_file($path, 200, 82);

        $this->assertSame('skipped_memory', $status);
        $this->assertSame($original, file_get_contents($path));
    }

    public function test_compress_image_file_keeps_original_and_leaves_no_temp_on_exception(): void
    {
        $path = $this->makeJpeg($this->uploadRoot . '/photo.jpg', 400, 300);
        $original = file_get_contents($path);

        $editor = Mockery::mock();
        $editor->shouldReceive('maybe_exif_rotate')->zeroOrMoreTimes()->andReturn(true);
        $editor->shouldReceive('set_quality')->zeroOrMoreTimes()->andThrow(new \RuntimeException('boom'));
        Functions\when('wp_get_image_editor')->justReturn($editor);

        $status = \Proud\Gform\proud_gform_compress_image_file($path, 200, 82);

        $this->assertSame('failed_exception', $status);
        $this->assertSame($original, file_get_contents($path));
        $this->assertCount(1, glob($this->uploadRoot . '/*'), 'No temp file should remain after a thrown exception.');
    }

    public function test_compress_image_file_sets_permissions_after_successful_rename(): void
    {
        $path = $this->makeJpeg($this->uploadRoot . '/photo.jpg', 400, 300);
        Functions\when('wp_get_image_editor')->justReturn($this->mockEditor());

        \Proud\Gform\proud_gform_compress_image_file($path, 200, 82);

        $this->assertSame([$path], GFFormsModel::$permission_calls);
    }

    // -------------------------------------------------------------------------
    // gform_compress_image_upload() -- top-level filter callback
    // -------------------------------------------------------------------------

    public function test_upload_filter_returns_value_unchanged(): void
    {
        $file = $this->makeJpeg($this->tmpDir . '/src.jpg', 2400, 1600);
        [, $url] = $this->placeInUploadRoot($file, '1-abc/photo.jpg');

        Functions\when('wp_get_image_editor')->justReturn($this->mockEditor());

        $field = $this->mockField();
        $result = \Proud\Gform\gform_compress_image_upload($url, [], $field, ['id' => 1]);

        $this->assertSame($url, $result, 'The value must be returned unchanged; only bytes on disk change.');
    }

    public function test_upload_filter_skips_when_field_disabled(): void
    {
        $file = $this->makeJpeg($this->tmpDir . '/src.jpg', 2400, 1600);
        [$target, $url] = $this->placeInUploadRoot($file, '1-abc/photo.jpg');
        $original = file_get_contents($target);

        Functions\expect('wp_get_image_editor')->never();

        $field = $this->mockField('fileupload', false);
        \Proud\Gform\gform_compress_image_upload($url, [], $field, ['id' => 1]);

        $this->assertSame($original, file_get_contents($target));
    }

    public function test_upload_filter_runs_when_toggle_unset(): void
    {
        $file = $this->makeJpeg($this->tmpDir . '/src.jpg', 2400, 1600);
        [$target, $url] = $this->placeInUploadRoot($file, '1-abc/photo.jpg');

        Functions\when('wp_get_image_editor')->justReturn($this->mockEditor());

        $field = $this->mockField('fileupload');
        \Proud\Gform\gform_compress_image_upload($url, [], $field, ['id' => 1]);

        $this->assertSame(1, filesize($target), 'Compression must run by default when the field has never set the toggle.');
    }

    public function test_upload_filter_ignores_non_fileupload_field(): void
    {
        Functions\expect('wp_get_image_editor')->never();

        $field = $this->mockField('text');
        $result = \Proud\Gform\gform_compress_image_upload('some value', [], $field, ['id' => 1]);

        $this->assertSame('some value', $result);
    }

    public function test_upload_filter_ignores_empty_value(): void
    {
        Functions\expect('wp_get_image_editor')->never();

        $field = $this->mockField();
        $result = \Proud\Gform\gform_compress_image_upload('', [], $field, ['id' => 1]);

        $this->assertSame('', $result);
    }

    public function test_upload_filter_compresses_every_image_in_multi_file_json_and_skips_pdf(): void
    {
        $jpeg1 = $this->makeJpeg($this->tmpDir . '/a.jpg', 2400, 1600);
        $jpeg2 = $this->makeJpeg($this->tmpDir . '/b.jpg', 2400, 1600);
        $pdf   = $this->makePdf($this->tmpDir . '/c.pdf');

        [$t1, $u1] = $this->placeInUploadRoot($jpeg1, '1-abc/a.jpg');
        [$t2, $u2] = $this->placeInUploadRoot($jpeg2, '1-abc/b.jpg');
        [$t3, $u3] = $this->placeInUploadRoot($pdf, '1-abc/c.pdf');
        $pdfOriginal = file_get_contents($t3);

        Functions\when('wp_get_image_editor')->justReturn($this->mockEditor());

        $value = json_encode([$u1, $u2, $u3]);
        $field = $this->mockField();
        $result = \Proud\Gform\gform_compress_image_upload($value, [], $field, ['id' => 1]);

        $this->assertSame($value, $result);
        $this->assertSame(1, filesize($t1));
        $this->assertSame(1, filesize($t2));
        $this->assertSame($pdfOriginal, file_get_contents($t3), 'The PDF must be left untouched.');
    }

    public function test_upload_filter_compresses_json_single_file_value(): void
    {
        $jpeg = $this->makeJpeg($this->tmpDir . '/a.jpg', 2400, 1600);
        [$target, $url] = $this->placeInUploadRoot($jpeg, '1-abc/a.jpg');

        Functions\when('wp_get_image_editor')->justReturn($this->mockEditor());

        $value = json_encode([$url]);
        $field = $this->mockField();
        $result = \Proud\Gform\gform_compress_image_upload($value, [], $field, ['id' => 1]);

        $this->assertSame($value, $result);
        $this->assertSame(1, filesize($target));
    }

    public function test_upload_filter_ignores_create_lead_tmp_array_shape_without_error(): void
    {
        Functions\expect('wp_get_image_editor')->never();

        $value = json_encode([
            ['tmp_name' => 'tmp/abc', 'uploaded_filename' => 'photo.jpg'],
        ]);

        $field = $this->mockField();
        $result = \Proud\Gform\gform_compress_image_upload($value, [], $field, ['id' => 1]);

        $this->assertSame($value, $result);
    }

    public function test_upload_filter_rejects_gcs_url_without_error(): void
    {
        Functions\expect('wp_get_image_editor')->never();

        $url = 'https://storage.googleapis.com/proudcity/somesite/uploads/gravity_forms/1-abc/photo.jpg';
        $field = $this->mockField();
        $result = \Proud\Gform\gform_compress_image_upload($url, [], $field, ['id' => 1]);

        $this->assertSame($url, $result);
    }

    public function test_upload_filter_honours_max_dimension_and_quality_filters(): void
    {
        $file = $this->makeJpeg($this->tmpDir . '/src.jpg', 2400, 1600);
        [$target, $url] = $this->placeInUploadRoot($file, '1-abc/photo.jpg');

        $capturedQuality = null;
        $editor = Mockery::mock();
        $editor->shouldReceive('maybe_exif_rotate')->once()->andReturn(true);
        $editor->shouldReceive('set_quality')->once()->andReturnUsing(function ($q) use (&$capturedQuality) {
            $capturedQuality = $q;
            return true;
        });
        $editor->shouldReceive('resize')->once()->with(500, 500, false)->andReturn(true);
        $editor->shouldReceive('save')->once()->andReturnUsing(static function ($destination, $mime) {
            file_put_contents($destination, 'x');
            return ['path' => $destination];
        });

        Functions\when('wp_get_image_editor')->justReturn($editor);
        Functions\when('apply_filters')->alias(static function ($tag, $value, ...$args) {
            if ('proud_gform_image_max_dimension' === $tag) {
                return 500;
            }
            if ('proud_gform_image_quality' === $tag) {
                return 40;
            }
            return $value;
        });

        $field = $this->mockField();
        \Proud\Gform\gform_compress_image_upload($url, [], $field, ['id' => 1]);

        $this->assertSame(40, $capturedQuality);
    }

    public function test_upload_filter_clamps_out_of_range_filter_values(): void
    {
        $file = $this->makeJpeg($this->tmpDir . '/src.jpg', 2400, 1600);
        [$target, $url] = $this->placeInUploadRoot($file, '1-abc/photo.jpg');

        $capturedQuality = null;
        $editor = Mockery::mock();
        $editor->shouldReceive('maybe_exif_rotate')->once()->andReturn(true);
        $editor->shouldReceive('set_quality')->once()->andReturnUsing(function ($q) use (&$capturedQuality) {
            $capturedQuality = $q;
            return true;
        });
        $editor->shouldReceive('resize')->once()->with(1, 1, false)->andReturn(true);
        $editor->shouldReceive('save')->once()->andReturnUsing(static function ($destination, $mime) {
            file_put_contents($destination, 'x');
            return ['path' => $destination];
        });

        Functions\when('wp_get_image_editor')->justReturn($editor);
        Functions\when('apply_filters')->alias(static function ($tag, $value, ...$args) {
            if ('proud_gform_image_max_dimension' === $tag) {
                return -50;
            }
            if ('proud_gform_image_quality' === $tag) {
                return 999;
            }
            return $value;
        });

        $field = $this->mockField();
        \Proud\Gform\gform_compress_image_upload($url, [], $field, ['id' => 1]);

        $this->assertSame(100, $capturedQuality);
    }

    /**
     * The whole callback body is wrapped in try/catch(\Throwable): a thrown
     * exception anywhere inside must never surface, and the original value
     * must still be returned so the submission is never blocked.
     */
    public function test_upload_filter_never_throws_and_returns_value_unchanged(): void
    {
        $file = $this->makeJpeg($this->tmpDir . '/src.jpg', 2400, 1600);
        [, $url] = $this->placeInUploadRoot($file, '1-abc/photo.jpg');

        Functions\when('wp_get_image_editor')->alias(static function () {
            throw new \RuntimeException('unexpected');
        });

        $field = $this->mockField();
        $result = \Proud\Gform\gform_compress_image_upload($url, [], $field, ['id' => 1]);

        $this->assertSame($url, $result);
    }

    // -------------------------------------------------------------------------
    // Hook registration -- source inspection (GFCommon can't be loaded here)
    // -------------------------------------------------------------------------

    /**
     * gform_compress_image_upload() must be registered on gform_save_field_value
     * at priority 5, positioned above both the proud_gform_stateless_available()
     * and proud_gform_stateless_active() gates, so it always runs before either
     * the Stateless addon (priority 10) or the legacy bridge (priority 100)
     * uploads/rewrites the value.
     */
    public function test_hook_registered_at_priority_5_above_both_gates(): void
    {
        $source = file_get_contents(__DIR__ . '/../plugin_override/gravityforms/proud-gravityforms.php');

        // Same __NAMESPACE__ . '\\callback' style already used elsewhere in
        // this file (e.g. gform_confirmation_anchor_alter above), so the raw
        // source bytes carry two backslashes for the one that survives
        // single-quote parsing.
        $needle = "add_filter('gform_save_field_value', __NAMESPACE__ . '\\\\gform_compress_image_upload', 5, 4);";

        $this->assertStringContainsString(
            $needle,
            $source,
            'gform_compress_image_upload must be registered on gform_save_field_value at priority 5.'
        );

        $registrationPos  = strpos($source, $needle);
        $availableGatePos = strpos($source, 'proud_gform_stateless_available()) {');
        $activeGatePos    = strpos($source, 'proud_gform_stateless_active()) {');

        $this->assertNotFalse($availableGatePos);
        $this->assertNotFalse($activeGatePos);
        $this->assertLessThan($availableGatePos, $registrationPos, 'Registration must come before the stateless_available() gate.');
        $this->assertLessThan($activeGatePos, $registrationPos, 'Registration must come before the stateless_active() gate.');
    }
}
