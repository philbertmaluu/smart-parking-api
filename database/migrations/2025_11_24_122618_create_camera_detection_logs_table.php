<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('camera_detection_logs', function (Blueprint $table) {
            $table->id();
            
            // API response fields
            $table->integer('camera_detection_id')->comment('ID from camera API');
            $table->string('numberplate')->index()->comment('Detected plate number');
            $table->string('originalplate')->nullable()->comment('Original plate number from camera');
            $table->timestamp('detection_timestamp')->index()->comment('Timestamp from camera');
            $table->timestamp('utc_time')->nullable()->comment('UTC time from camera');
            
            // Detection quality metrics
            $table->boolean('located_plate')->default(false)->comment('Whether plate was located');
            $table->decimal('global_confidence', 5, 2)->nullable()->comment('Confidence percentage');
            $table->decimal('average_char_height', 8, 2)->nullable()->comment('Average character height');
            $table->integer('process_time')->nullable()->comment('Processing time in ms');
            
            // Plate details
            $table->integer('plate_format')->nullable();
            $table->integer('country')->nullable();
            $table->string('country_str')->nullable();
            
            // Vehicle position (bounding box)
            $table->integer('vehicle_left')->default(0);
            $table->integer('vehicle_top')->default(0);
            $table->integer('vehicle_right')->default(0);
            $table->integer('vehicle_bottom')->default(0);
            
            // Result position (bounding box)
            $table->integer('result_left')->default(0);
            $table->integer('result_top')->default(0);
            $table->integer('result_right')->default(0);
            $table->integer('result_bottom')->default(0);
            
            // Vehicle details
            $table->decimal('speed', 8, 2)->nullable()->default(0.00);
            $table->integer('lane_id')->nullable();
            $table->integer('direction')->nullable()->comment('0 = entry, 1 = exit');
            $table->integer('make')->nullable();
            $table->integer('model')->nullable();
            $table->integer('color')->nullable();
            $table->string('make_str')->nullable();
            $table->string('model_str')->nullable();
            $table->string('color_str')->nullable();
            $table->string('veclass_str')->nullable();
            
            // Image paths
            $table->string('image_path')->nullable()->comment('Full image path on camera');
            $table->string('image_retail_path')->nullable()->comment('Cropped plate image path');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            
            // List information
            $table->string('list_id')->nullable();
            $table->string('name_list_id')->nullable();
            
            // Additional metadata
            $table->integer('evidences')->default(0);
            $table->integer('br_ocurr')->default(0);
            $table->integer('br_time')->default(0);
            
            // Store raw JSON response for reference
            $table->json('raw_data')->nullable()->comment('Complete raw JSON response from API');
            
            // Status tracking
            $table->boolean('processed')->default(false)->index()->comment('Whether this detection has been processed');
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_notes')->nullable();
            
            $table->timestamps();
            
            // Indexes for common queries (numberplate already indexed above)
            $table->index(['processed', 'detection_timestamp']);
            $table->index('camera_detection_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('camera_detection_logs');
    }
};
