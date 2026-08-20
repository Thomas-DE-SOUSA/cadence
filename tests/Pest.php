<?php

declare(strict_types=1);

/*
 * Pest configuration. Feature tests use the full framework TestCase;
 * Unit tests (use-case level) stay POPO and rely on in-memory fakes.
 */

pest()->extend(Tests\TestCase::class)->in('Feature');
