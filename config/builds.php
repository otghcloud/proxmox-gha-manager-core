<?php

return [

    'image_builder_path' => env('IMAGE_BUILDER_PATH', dirname(base_path()).'/proxmox-gha-manager-templates'),

    'log_directory' => env('BUILD_LOG_DIRECTORY', storage_path('app/builds')),

    'working_directory' => env('BUILD_WORKING_DIRECTORY', storage_path('app')),

    'packer_plugin_path' => env('PACKER_PLUGIN_PATH', storage_path('app/packer-plugins')),

    'packer_binary' => env('PACKER_BINARY', 'packer'),

    // Only used when the installed templates bundle no vendor/runner-images tree of their own.
    'runner_images_path' => env('RUNNER_IMAGES_PATH', storage_path('app/runner-images')),

    'runner_images_repository' => env('RUNNER_IMAGES_REPOSITORY', 'https://github.com/actions/runner-images.git'),
];
