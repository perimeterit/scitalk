<?php

namespace Drupal\scitalk_base\Plugin\Validation\Constraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UniqueTalkNumber constraint.
 */
class UniqueTalkNumberConstraintValidator extends ConstraintValidator {
  private $entity_type = 'talk';

  /**
   * {@inheritdoc}
   */
  public function validate($item, Constraint $constraint) {
    if (!isset($item)) {
      return;
    }
    $entity = $item->getEntity();
    if ($entity->bundle() == $this->entity_type) {
      $isUnique = true;

      //new requirement: unique is now a talk number + source
      $source_target_id = $entity->get('field_talk_source_repository')->target_id ?? 0;
      $source_field = \Drupal::entityTypeManager()->getStorage('group')->load($source_target_id);
      $source_name = $source_field->label->value ?? '';

      //If the entity already has an id then we're in an entity *update* operation
      //make sure that (in case they are changing the talk number) that no other entity has the same talk number
      if ($entity->id()) {
        $isUnique = $this->isUnique( $entity->field_talk_number->value, $source_name, $entity->id() );
      }
      else {
        $isUnique = $this->isUnique( $entity->field_talk_number->value, $source_name );
      }

      if (!$isUnique) {
        $this->context->addViolation( t($constraint->notUnique, ['%value' => $entity->field_talk_number->value]) );
      }
    }
  }

  /**
   * Is unique?
   *
   * @param string $value
   */
  private function isUnique($talk_number, $source_name, $id = '') {
    //we've added a new condition to allow duplicate IDs as long as they belong to different sources
    if($talk_number !== NULL) {
      $query_count = \Drupal::entityQuery('node')
           ->condition('type', $this->entity_type)
           ->condition('field_talk_number', $talk_number, '=');
  
      if (empty($source_name)) {
        $query_count->notExists('field_talk_source_repository.entity.label');
      }
      else {
        $query_count->condition('field_talk_source_repository.entity.label', $source_name);
      }
      
      if (!empty($id)) {
        $query_count->condition('nid', $id, '<>');
      }
  
      $query_count->count();
  
      return $query_count->execute() == 0;
    }
    else {
      return TRUE;  //NULL talk number is true?
    }
  }

}
