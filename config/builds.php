<?php

return [

    'image_builder_path' => env('IMAGE_BUILDER_PATH', dirname(base_path()).'/proxmox-gha-manager-templates'),

    'log_directory' => env('BUILD_LOG_DIRECTORY', storage_path('app/builds')),

    'packer_plugin_path' => env('PACKER_PLUGIN_PATH', storage_path('app/packer-plugins')),
];
