<?php

/*
 * Configure uploading photos using BuddyPress methods
 * https://codex.buddypress.org/plugindev/bp_attachment/
 * https://github.com/imath/custom-file-for-messages/blob/master/includes/class-custom-attachment.php
 */

class ANS_Photo_Attachment extends BP_Attachment {

    public function __construct() {
        // Set the Custom Attachment parameters
        parent::__construct([
            'action' => 'photos_upload',
            'file_input' => 'photos',
            'allowed_mime_types' => ['jpg', 'png'],
            'upload_error_strings' => [
                9 => 'Превышено максимальное число фотографий',
            ],
            'base_dir' => 'ans-my-photos',
        ]);
    }

    // Validations
    public function validate_upload($file = []) {
        // BP validations
        $file = parent::validate_upload($file);

        // Bail if already an error
        if (!empty($file['error'])) {
            return $file;
        }

        // If the number of uploaded files exceeded add an error
        $uploaded_files = glob($this->upload_dir_filter()['path'] . '/*-ansfull.jpg');

        if (count($uploaded_files) >= ANS_BP_Photos::MAX_PHOTO_NUM) {
            $file['error'] = 9;
        }

        return $file;
    }

    // Add a subdirectory for each user ids
    public function upload_dir_filter($upload_dir = []) {
        return [
            'path' => $this->upload_path . '/' . bp_displayed_user_id(),
            'url' => $this->url . '/' . bp_displayed_user_id(),
            'subdir' => '/' . bp_displayed_user_id(),
            'basedir' => $this->upload_path . '/' . bp_displayed_user_id(),
            'baseurl' => $this->url . '/' . bp_displayed_user_id(),
            'error' => false,
        ];
    }

}
