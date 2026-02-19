# PixelPapa WordPress Plugin

AI-powered image enhancement and video generation using Claid.ai API.

## Features

- ✨ **Image Enhancement** — Deblur, noise reduction, color correction, HDR
- 🎬 **Video Generation** — Turn images into AI-generated videos
- 🔍 **Background Removal** — Remove or blur backgrounds
- 📊 **Bulk Processing** — Process multiple images at once
- 🔔 **Webhook Support** — Real-time updates via Claid.ai webhooks
- ⏱️ **Queue System** — Reliable job processing with retry logic

## Requirements

- WordPress 5.9+
- PHP 7.4+
- Claid.ai API key

## Installation

1. Upload plugin to `/wp-content/plugins/pixelpapa/`
2. Activate plugin
3. Go to **Settings → PixelPapa AI**
4. Enter your Claid.ai API key
5. Configure webhook URL in Claid.ai dashboard:
   ```
   https://yoursite.com/wp-json/pixelpapa/v1/webhook
   ```

## Usage

### Single Image Enhancement

1. Go to **Media Library**
2. Hover over image → click **AI Enhance**
3. Or click image → use buttons in right sidebar

### Bulk Enhancement

1. Select multiple images in Media Library
2. Choose **AI Enhance Selected** from bulk actions dropdown
3. Click **Apply**

### Video Generation

1. Select image in Media Library
2. Click **Generate Video**
3. Wait for processing (5-10 seconds)

## API Reference

### Database Table

Table: `{prefix}_pixelpapa_jobs`

| Column | Description |
|--------|-------------|
| `attachment_id` | Original image ID |
| `job_type` | enhance, upscale, video |
| `status` | pending, processing, done, error |
| `api_job_id` | Claid API job ID |
| `result_attachment_id` | Processed file ID |

### Hooks

```php
// Job completed
do_action('pixelpapa_job_completed', $job_id, $result_attachment_id);

// Job failed
do_action('pixelpapa_job_failed', $job_id, $error_message);
```

## Credits

Uses Claid.ai API for image processing and video generation.
