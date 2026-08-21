<?php

declare(strict_types=1);

use Cadence\Activity\Infrastructure\Gpx\GpxParser;
use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function sampleGpx(int $points = 80): string
{
    $trkpts = '';
    $t = strtotime('2026-08-25T08:00:00Z');
    for ($i = 0; $i < $points; $i++) {
        $lon = number_format(2.35 + $i * 0.0002, 7, '.', '');
        $ele = round(40 + sin($i / 8) * 5, 1);
        $time = gmdate('Y-m-d\TH:i:s\Z', $t + $i * 5);
        $trkpts .= "<trkpt lat=\"48.8500000\" lon=\"{$lon}\"><ele>{$ele}</ele><time>{$time}</time></trkpt>";
    }

    return '<?xml version="1.0"?><gpx xmlns="http://www.topografix.com/GPX/1/1"><trk><trkseg>'.$trkpts.'</trkseg></trk></gpx>';
}

describe('GPX import', function (): void {
    it('parses a GPX into a full activity summary', function (): void {
        $summary = GpxParser::summary(sampleGpx());

        expect($summary)->not->toBeNull();
        expect($summary['distanceMeters'])->toBeGreaterThan(1050)->toBeLessThan(1300);
        expect($summary['movingSeconds'])->toBeGreaterThan(300);
        expect($summary['splits'])->not->toBeEmpty();
        expect($summary['track'])->not->toBeEmpty();
        expect($summary['stream'])->not->toBeEmpty();
        // Splits cover the total distance.
        expect(array_sum(array_column($summary['splits'], 'distanceMeters')))->toBe($summary['distanceMeters']);
    });

    it('creates an activity from an uploaded GPX with its route and profile', function (): void {
        $file = UploadedFile::fake()->createWithContent('run.gpx', sampleGpx());

        $this->post('/activites/importer-gpx', ['gpx' => $file])->assertRedirect();

        $activity = ActivityModel::query()->first();
        expect($activity)->not->toBeNull();
        expect($activity->source)->toBe('GPX');
        expect($activity->track)->not->toBeNull();
        expect($activity->stream)->not->toBeNull();
        expect($activity->distance_meters)->toBeGreaterThan(1050);
    });

    it('rejects a file without a usable track', function (): void {
        $file = UploadedFile::fake()->createWithContent('bad.gpx', '<gpx></gpx>');

        $this->post('/activites/importer-gpx', ['gpx' => $file])->assertSessionHasErrors('gpx');
        expect(ActivityModel::query()->count())->toBe(0);
    });
});
