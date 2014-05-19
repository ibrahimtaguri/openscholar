<?php

/**
 * @file
 * Contains OsProfilesResource.
 */

class OsProfilesResource extends RestfulEntityBaseNode {

  /**
   * Overrides RestfulEntityBase::getPublicFields().
   */
  public function getPublicFields() {
    $public_fields = parent::getPublicFields();

    $public_fields['description'] = array(
      'property' => 'body',
      'sub_property' => 'value',
    );

    $public_fields['address'] = array(
      'property' => 'field_address',
    );

    $public_fields['email'] = array(
      'property' => 'field_email',
    );

    $public_fields['first_name'] = array(
      'property' => 'field_first_name',
    );

    $public_fields['middle_name'] = array(
      'property' => 'field_middle_name_or_initial',
    );

    $public_fields['last_name'] = array(
      'property' => 'field_last_name',
    );

    $public_fields['phone'] = array(
      'property' => 'field_phone',
    );

    $public_fields['professional_title'] = array(
      'property' => 'field_professional_title',
    );

    $public_fields['website'] = array(
      'property' => 'field_website',
    );

    $public_fields['photo'] = array(
      'property' => 'field_person_photo',
      'process_callback' => array($this, 'processPhoto'),
    );

    $public_fields['uuid'] = array(
      'property' => 'field_uuid',
    );

    return $public_fields;
  }

  /**
   * Process callback; Return the URI value of an image.
   */
  protected function processPhoto($value) {
    return array(
      'cropbox_height' => $value['cropbox_height'],
      'cropbox_width' => $value['cropbox_width'],
      'cropbox_x' => $value['cropbox_x'],
      'cropbox_y' => $value['cropbox_y'],
      'uri' => file_create_url($value['uri']),
    );
  }
}
