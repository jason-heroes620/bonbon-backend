<?php

return [
    /*
     * When true, gallery images are optimized on the queue (requires a running
     * `php artisan queue:work` worker). When false (default), optimization runs
     * inline during upload so uploads work without a queue worker.
     */
    'async_processing' => env('GALLERY_ASYNC_PROCESSING', false),

    // Thumbnail target (grid) and optimized viewer target.
    'thumbnail_max_width' => 400,
    'thumbnail_max_bytes' => 81920,
    'optimized_max_width' => 1600,
    'optimized_max_bytes' => 512000,
];
