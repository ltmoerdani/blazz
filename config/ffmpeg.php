<?php 

return [
    'ffmpeg' => [
        'binaries' => env('FFMPEG_BINARIES', '/opt/homebrew/bin/ffmpeg')
    ],
    'ffprobe' =>  [
        'binaries' => env('FFPROBE_BINARIES', '/opt/homebrew/bin/ffprobe')
    ],
    'timeout' => 3600, // Timeout in seconds
    'threads' => 12, // Number of threads
];
