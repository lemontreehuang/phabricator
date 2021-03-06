<?php

final class DiffusionCommitRequiredActionResultBucket
  extends DiffusionCommitResultBucket {

  const BUCKETKEY = 'action';

  private $objects;

  public function getResultBucketName() {
    return pht('Bucket by Required Action');
  }

  protected function buildResultGroups(
    PhabricatorSavedQuery $query,
    array $objects) {

    $this->objects = $objects;

    $phids = $query->getEvaluatedParameter('responsiblePHIDs', array());
    if (!$phids) {
      throw new Exception(
        pht(
          'You can not bucket results by required action without '.
          'specifying "Responsible Users".'));
    }
    $phids = array_fuse($phids);

    $groups = array();

    $groups[] = $this->newGroup()
      ->setName(pht('Needs Attention'))
      ->setNoDataString(pht('None of your commits have active concerns.'))
      ->setObjects($this->filterConcernRaised($phids));

    $groups[] = $this->newGroup()
      ->setName(pht('Ready to Audit'))
      ->setNoDataString(pht('No commits are waiting for you to audit them.'))
      ->setObjects($this->filterShouldAudit($phids));

    $groups[] = $this->newGroup()
      ->setName(pht('Waiting on Authors'))
      ->setNoDataString(pht('None of your audits are waiting on authors.'))
      ->setObjects($this->filterWaitingOnAuthors($phids));

    $groups[] = $this->newGroup()
      ->setName(pht('Waiting on Auditors'))
      ->setNoDataString(pht('None of your commits are waiting on audit.'))
      ->setObjects($this->filterWaitingOnAuditors($phids));

    // Because you can apply these buckets to queries which include revisions
    // that have been closed, add an "Other" bucket if we still have stuff
    // that didn't get filtered into any of the previous buckets.
    if ($this->objects) {
      $groups[] = $this->newGroup()
        ->setName(pht('Other Commits'))
        ->setObjects($this->objects);
    }

    return $groups;
  }

  private function filterConcernRaised(array $phids) {
    $results = array();
    $objects = $this->objects;

    $status_concern = PhabricatorAuditCommitStatusConstants::CONCERN_RAISED;

    foreach ($objects as $key => $object) {
      if (empty($phids[$object->getAuthorPHID()])) {
        continue;
      }

      if ($object->getAuditStatus() != $status_concern) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterShouldAudit(array $phids) {
    $results = array();
    $objects = $this->objects;

    $should_audit = array(
      PhabricatorAuditStatusConstants::AUDIT_REQUIRED,
      PhabricatorAuditStatusConstants::AUDIT_REQUESTED,
    );
    $should_audit = array_fuse($should_audit);

    foreach ($objects as $key => $object) {
      if (!$this->hasAuditorsWithStatus($object, $phids, $should_audit)) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterWaitingOnAuthors(array $phids) {
    $results = array();
    $objects = $this->objects;

    $status_concern = PhabricatorAuditCommitStatusConstants::CONCERN_RAISED;

    foreach ($objects as $key => $object) {
      if ($object->getAuditStatus() != $status_concern) {
        continue;
      }

      if (isset($phids[$object->getAuthorPHID()])) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

  private function filterWaitingOnAuditors(array $phids) {
    $results = array();
    $objects = $this->objects;

    $status_waiting = array(
      PhabricatorAuditCommitStatusConstants::NEEDS_AUDIT,
      PhabricatorAuditCommitStatusConstants::PARTIALLY_AUDITED,
    );
    $status_waiting = array_fuse($status_waiting);

    foreach ($objects as $key => $object) {
      if (empty($status_waiting[$object->getAuditStatus()])) {
        continue;
      }

      $results[$key] = $object;
      unset($this->objects[$key]);
    }

    return $results;
  }

}
