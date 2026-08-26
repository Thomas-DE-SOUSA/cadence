<?php

declare(strict_types=1);

use Cadence\Activity\Application\Port\ActivityPhotoParser;
use Cadence\Activity\Application\Port\ParsedActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

it('creates an activity from a photo (vision parser faked)', function (): void {
    app()->bind(ActivityPhotoParser::class, fn (): ActivityPhotoParser => new class implements ActivityPhotoParser
    {
        public function parse(string $imageBytes, string $mimeType): ParsedActivity
        {
            return new ParsedActivity('2026-08-20T10:00:00+00:00', 8000, 2400, 2400, 20, [], []);
        }
    });

    // A real 1x1 PNG (GD isn't available to fake one), so the `image` rule passes.
    $path = tempnam(sys_get_temp_dir(), 'watch').'.png';
    file_put_contents($path, (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC',
    ));
    $photo = new UploadedFile($path, 'watch.png', 'image/png', null, true);

    $this->post('/activites/importer-photo', ['photo' => $photo])->assertRedirect();

    $this->assertDatabaseHas('activities', ['tenant_id' => 'tenant-thomas', 'distance_meters' => 8000, 'moving_seconds' => 2400]);
});

it('rejects a non-image upload', function (): void {
    $this->from('/activites/nouvelle')
        ->post('/activites/importer-photo', ['photo' => UploadedFile::fake()->create('run.txt', 10, 'text/plain')])
        ->assertRedirect('/activites/nouvelle')
        ->assertSessionHasErrors('photo');
});
