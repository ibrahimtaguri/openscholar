<?php

class OsVocabulary extends \RestfulEntityBase {

  /**
   * @api {get} api/vocabulary?vsite=:id Get
   * @apiVersion 0.1.0
   * @apiName Get
   * @apiGroup Vocabulary
   *
   * @apiDescription Consume vocabulary content.
   *
   * @apiParam {Integer} id The ID of the VSite
   */
  public function publicFieldsInfo() {
    $fields = parent::publicFieldsInfo();

    $fields['machine_name'] = array(
      'property' => 'machine_name',
    );

    $fields['vsite'] = array(
      'property' => 'vsite',
    );

    return $fields;
  }

  /**
   * @api {post} api/vocabulary Post
   * @apiVersion 0.1.0
   * @apiName Post
   * @apiGroup Vocabulary
   *
   * @apiDescription Create vocabulary content.
   *
   * @apiParam {String} label The name of the vocabulary
   * @apiParam {Integer} id The ID of the VSite
   */
  public function createEntity() {
    return parent::createEntity();
  }

  /**
   * @api {delete} api/vocabulary/:id Delete
   * @apiVersion 0.1.0
   * @apiName Delete
   * @apiGroup Vocabulary
   *
   * @apiDescription Delete a vocabulary.
   *
   * @apiParam {Integer} id The vocabulary ID
   *
   * @apiSampleRequest off
   */
  public function deleteEntity($entity_id) {
    parent::deleteEntity($entity_id);
  }

  /**
   * @api {patch} api/vocabulary/:id Patch
   * @apiVersion 0.1.0
   * @apiName Patch
   * @apiGroup Vocabulary
   *
   * @apiDescription Patch a vocabulary.
   *
   * @apiParam {String} label The name of the vocabulary
   * @apiParam {Integer} id The vocabulary ID
   *
   * @apiSampleRequest off
   */
  public function patchEntity($entity_id) {
    parent::patchEntity($entity_id);
  }

  /**
   * {@inheritdoc}
   */
  public function entityValidate(\EntityMetadataWrapper $wrapper) {
    if ($this->getMethod() == \RestfulBase::POST) {
      if (empty($this->request['vsite'])) {
        throw new \RestfulForbiddenException('You need to provide vsite ID.');
      }
    }

    parent::entityValidate($wrapper);
  }


  /**
   * {@inheritdoc}
   */
  protected function checkEntityAccess($op, $entity_type, $entity) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkPropertyAccess($op, $public_field_name, EntityMetadataWrapper $property_wrapper, EntityMetadataWrapper $wrapper) {
    // Set the user account.
    $this->getAccount();

    if ($this->getMethod() == \RestfulBase::GET) {
      // Always return TRUE for properties.
      return TRUE;
    }

    if ($public_field_name != 'vsite') {
      return TRUE;
    }

    if ($this->getMethod() == \RestfulBase::POST) {
      if (!$vsite = vsite_get_vsite($this->request['vsite'])) {
        throw new \RestfulForbiddenException('There is no vsite with the provided ID.');
      }
    }
    else {
      $vsite = vsite_get_vsite(og_vocab_relation_get($this->path)->gid);
    }

    spaces_set_space($vsite);

    if (!vsite_og_user_access('administer taxonomy')) {
      throw new \RestfulForbiddenException('You are not allowed to manage vocabularies.');
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function entityPreSave(\EntityMetadataWrapper $wrapper) {

    if ($this->getMethod() == \RestfulInterface::POST) {
      // This is a new vocab. Check if we have relationship between the vocab
      // and the group.
      $query = db_select('og_vocab_relation', 'ogr');
      $query->join('taxonomy_vocabulary', 'v', 'v.vid = ogr.vid');

      // We need to check if a vocabulary with that name exists in the group.
      $result = $query
        ->fields('v')
        ->condition('group_type', 'node')
        ->condition('gid', $wrapper->vsite->value())
        ->condition('v.machine_name', $wrapper->machine_name->value())
        ->execute()
        ->fetchAllAssoc('vid');

      if ($result) {
        // The vocabulary is already exists, we can use him.
        throw new \RestfulBadRequestException('The vocabulary already exists in the group.');
      }
      else {
        // We didn't found any vocabulary - create a new one.
        $i = 0;
        $machine_name = str_replace(array(' ', '-', ','), array('_', '_', ''), strtolower($wrapper->machine_name->value()));
        while (taxonomy_vocabulary_machine_name_load($machine_name)) {
          $machine_name = substr($machine_name, 0, 32 - strlen($i)) . $i;
          $i++;
        }
      }

      $wrapper->machine_name->set($machine_name);
    }

    parent::entityPreSave($wrapper);
  }

  /**
   * {@inheritdoc}
   */
  protected function setPropertyValues(EntityMetadataWrapper $wrapper, $null_missing_fields = FALSE) {
    parent::setPropertyValues($wrapper, $null_missing_fields);

    if ($this->getMethod() == \RestfulInterface::POST) {
      og_vocab_relation_save($wrapper->vid->value(), 'node', $this->request['vsite']);
    }

    // Creating OG vocab.
    if (in_array($this->getMethod(), array(\RestfulBase::POST, \RestfulBase::PATCH, \RestfulBase::PUT))) {
      if (!empty($this->request['entity_type']) && !empty($this->request['bundle'])) {
        og_vocab_create_og_vocab($wrapper->vid->value(), $this->request['entity_type'], $this->request['bundle'])->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function queryForListFilter(\EntityFieldQuery $query) {
    // Display vocabs from the current vsite.
    if (empty($_GET['vsite'])) {
      throw new \RestfulBadRequestException(t('You need to provide a vsite.'));
    }

    if (!$vsite = vsite_get_vsite($this->request['vsite'])) {
      return;
    }

    module_load_include('inc', 'vsite_vocab', 'includes/taxonomy');
    $vocabs = array_keys(vsite_vocab_get_vocabularies($vsite));

    if (!empty($this->request['entity_type']) && !empty($this->request['bundle'])) {

      if (!$og_vocabs = $this->getOgVocabsByEntityBundle($this->request['entity_type'], $this->request['bundle'])) {
        return;
      }

      $filtered_vocabs = array();
      foreach ($og_vocabs as $og_vocab) {
        if (!in_array($og_vocab->vid, $vocabs)) {
          //
          continue;
        }

        $filtered_vocabs[] = $og_vocab->vid;
      }

      $vocabs = $filtered_vocabs;
    }

    $query->propertyCondition('vid', $vocabs, 'IN');
  }

  /**
   * Get the attached OG vocabs to specific entity type and bundle.
   *
   * @param $entity_type
   *   The entity type.
   * @param $bundle
   *   The bundle.
   * @param null $field_name
   *   The field name. Optional.
   *
   * @return array
   *   Array of og vocabs attached to entity type and bundle.
   */
  private function getOgVocabsByEntityBundle($entity_type, $bundle, $field_name = NULL) {
    $query = new EntityFieldQuery();
    $query->entityCondition('entity_type', 'og_vocab')
      ->propertyCondition('entity_type', $entity_type)
      ->propertyCondition('bundle', $bundle);

    if (!empty($field_name)) {
      $query->propertyCondition('field_name', $field_name);
    }

    $results = $query->execute();

    if (empty($results['og_vocab'])) {
      return;
    }

    return entity_load('og_vocab', array_keys($results['og_vocab']));
  }

}