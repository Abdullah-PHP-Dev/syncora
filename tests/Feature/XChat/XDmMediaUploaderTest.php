<?php

namespace Tests\Feature\XChat;

use App\Services\MessagingServices\XDmMediaUploader;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\TestCase;

/**
 * Legacy X DM file sends: v2 chunked media upload (initialize / append /
 * finalize / STATUS) with dm_* categories, per
 * https://docs.x.com/x-api/media/quickstart/media-upload-chunked
 */
class XDmMediaUploaderTest extends TestCase
{
    private const API = 'https://api.x.com/2/';
    // Smallest valid PNG / GIF headers, enough for finfo to detect the type.
    private const PNG = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x06\0\0\0\x1f\x15\xc4\x89";
    private const GIF = "GIF89a\x01\0\x01\0\x80\0\0\0\0\0\xff\xff\xff!\xf9\x04\x01\0\0\0\0,\0\0\0\0\x01\0\x01\0\0\x02\x02D\x01\0;";

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
    }

    public function test_image_is_initialized_appended_and_finalized_as_dm_image(): void
    {
        Http::fake([
            self::API . 'media/upload/initialize'  => Http::response(['data' => ['id' => '1880028106020515840', 'media_key' => '3_1880028106020515840']]),
            self::API . 'media/upload/*/append'    => Http::response(null, 204),
            self::API . 'media/upload/*/finalize'  => Http::response(['data' => ['id' => '1880028106020515840']]),
        ]);

        $mediaId = app(XDmMediaUploader::class)->upload('tok', self::PNG);

        $this->assertSame('1880028106020515840', $mediaId);
        Http::assertSentCount(3);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === self::API . 'media/upload/initialize'
            && $r->hasHeader('Authorization', 'Bearer tok')
            && $r['media_type'] === 'image/png'
            && $r['media_category'] === 'dm_image'
            && $r['total_bytes'] === strlen(self::PNG));
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/1880028106020515840/append')
            && $r->isMultipart()
            && collect($r->data())->contains(fn ($part) => $part['name'] === 'segment_index' && (string) $part['contents'] === '0')
            && collect($r->data())->contains(fn ($part) => $part['name'] === 'media' && $part['contents'] === self::PNG));
        Sleep::assertNeverSlept();
    }

    public function test_gif_uses_dm_gif_and_waits_for_processing(): void
    {
        Http::fake([
            self::API . 'media/upload/initialize' => Http::response(['data' => ['id' => '42']]),
            self::API . 'media/upload/42/append'  => Http::response(null, 204),
            self::API . 'media/upload/42/finalize' => Http::response(['data' => ['id' => '42', 'processing_info' => ['state' => 'pending', 'check_after_secs' => 2]]]),
            self::API . 'media/upload?*'          => Http::sequence()
                ->push(['data' => ['processing_info' => ['state' => 'in_progress', 'check_after_secs' => 1]]])
                ->push(['data' => ['processing_info' => ['state' => 'succeeded']]]),
        ]);

        $this->assertSame('42', app(XDmMediaUploader::class)->upload('tok', self::GIF));

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), 'initialize') && $r['media_category'] === 'dm_gif');
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'GET' && $r['command'] === 'STATUS' && $r['media_id'] === '42');
        Sleep::assertSequence([Sleep::for(2)->seconds(), Sleep::for(1)->second()]);
    }

    public function test_large_file_is_sent_in_4mb_chunks_with_increasing_segment_index(): void
    {
        Http::fake([
            self::API . 'media/upload/initialize' => Http::response(['data' => ['id' => '7']]),
            self::API . 'media/upload/7/append'   => Http::response(null, 204),
            self::API . 'media/upload/7/finalize' => Http::response(['data' => ['id' => '7', 'processing_info' => ['state' => 'succeeded']]]),
        ]);

        app(XDmMediaUploader::class)->upload('tok', str_repeat('x', 9 * 1024 * 1024), 'video/mp4');

        $appends = Http::recorded(fn (HttpRequest $r) => str_ends_with($r->url(), '/append'));
        $this->assertCount(3, $appends);
        $this->assertSame(['0', '1', '2'], $appends->map(fn ($pair) => (string) collect($pair[0]->data())->firstWhere('name', 'segment_index')['contents'])->values()->all());
    }

    public function test_failed_processing_is_reported(): void
    {
        Http::fake([
            self::API . 'media/upload/initialize' => Http::response(['data' => ['id' => '9']]),
            self::API . 'media/upload/9/append'   => Http::response(null, 204),
            self::API . 'media/upload/9/finalize' => Http::response(['data' => ['processing_info' => ['state' => 'failed', 'error' => ['message' => 'InvalidMedia']]]]),
        ]);

        $this->expectExceptionObject(new RuntimeException('X could not process this file: InvalidMedia.'));

        app(XDmMediaUploader::class)->upload('tok', 'video-bytes', 'video/mp4');
    }

    public function test_unsupported_and_oversized_files_never_reach_x(): void
    {
        Http::fake();

        try {
            app(XDmMediaUploader::class)->upload('tok', '%PDF-1.4 test', 'application/pdf');
            $this->fail('PDF should be rejected');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('only send images, GIFs and videos', $e->getMessage());
        }

        try {
            app(XDmMediaUploader::class)->upload('tok', str_repeat('x', 5 * 1024 * 1024 + 1), 'image/jpeg');
            $this->fail('6 MB image should be rejected');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('dm_image limit: 5 MB', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_403_on_initialize_explains_the_missing_permission(): void
    {
        Http::fake([self::API . 'media/upload/initialize' => Http::response(['title' => 'Forbidden', 'detail' => 'Forbidden'], 403)]);

        $this->expectExceptionMessage('Reconnect the X account in Channels so it has the media.write permission.');

        app(XDmMediaUploader::class)->upload('tok', self::PNG);
    }

    public function test_transient_503_on_initialize_is_retried(): void
    {
        Http::fake([
            self::API . 'media/upload/initialize' => Http::sequence()->push(['title' => 'Service Unavailable'], 503)->push(['data' => ['id' => '5']]),
            self::API . 'media/upload/5/append'   => Http::response(null, 204),
            self::API . 'media/upload/5/finalize' => Http::response(['data' => ['id' => '5']]),
        ]);

        $this->assertSame('5', app(XDmMediaUploader::class)->upload('tok', self::PNG));
        Http::assertSentCount(4);
    }
}
