<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for proudcity_gallery_ids_to_string() in modules/media-gallery-ids.php.
 *
 * The core Media Gallery widget passes 'include' as an array. WP Media Folder
 * 6.3.0 casts it to a string ("Array") and drops every image not attached to
 * the current post. The filter joins the array into comma-separated IDs first.
 *
 * See wp-proudcity#3033.
 */
class MediaGalleryIdsTest extends TestCase
{
    public function test_array_include_is_joined_in_order(): void
    {
        $out = proudcity_gallery_ids_to_string([
            'include' => [5162, 19461, 5164, 5165, 7420],
            'exclude' => '',
            'columns' => 3,
        ]);

        // Order matters: the widget sorts by post__in.
        $this->assertSame('5162,19461,5164,5165,7420', $out['include']);
        $this->assertSame('', $out['exclude']);
        $this->assertSame(3, $out['columns']);
    }

    public function test_array_exclude_is_joined(): void
    {
        $out = proudcity_gallery_ids_to_string(['include' => '', 'exclude' => [12, '34']]);

        $this->assertSame('12,34', $out['exclude']);
    }

    public function test_string_ids_are_left_alone(): void
    {
        $atts = ['include' => '5162, 19461', 'exclude' => '7'];

        $this->assertSame($atts, proudcity_gallery_ids_to_string($atts));
    }

    public function test_non_numeric_and_zero_ids_are_dropped(): void
    {
        $out = proudcity_gallery_ids_to_string(['include' => ['5162', 'abc', 0, '', '-7']]);

        $this->assertSame('5162,7', $out['include']);
    }

    public function test_empty_array_becomes_empty_string(): void
    {
        $out = proudcity_gallery_ids_to_string(['include' => []]);

        $this->assertSame('', $out['include']);
    }

    public function test_non_array_input_passes_through(): void
    {
        $this->assertSame('', proudcity_gallery_ids_to_string(''));
    }
}
