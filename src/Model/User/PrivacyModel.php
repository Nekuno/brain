<?php

namespace Model\User;

use Everyman\Neo4j\Label;
use Everyman\Neo4j\Node;
use Everyman\Neo4j\Query\Row;
use Model\Exception\ValidationException;
use Model\Neo4j\GraphManager;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PrivacyModel
{
    protected $gm;
    protected $metadata;
    protected $defaultLocale;

    public function __construct(GraphManager $graphManager, array $metadata, $defaultLocale)
    {
        $this->gm = $graphManager;
        $this->metadata = $metadata;
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * Returns the metadata for editing the privacy
     * @param null $locale Locale of the metadata
     * @return array
     */
    public function getMetadata($locale = null)
    {
        $locale = $this->getLocale($locale);
        $choiceOptions = $this->getChoiceOptions($locale);

        $publicMetadata = array();
        foreach ($this->metadata as $name => $values) {
            $publicField = $values;
            $publicField['label'] = $values['label'][$locale];

            if ($values['type'] === 'choice') {
                $publicField['choices'] = array();
                if (isset($choiceOptions[$name])) {
                    $publicField['choices'] = $choiceOptions[$name];
                }
            }

            $publicMetadata[$name] = $publicField;
        }

        return $publicMetadata;
    }

    /**
     * @param int $id
     * @return array
     * @throws NotFoundHttpException
     */
    public function getById($id)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)<-[:PRIVACY_OF]-(privacy:Privacy)')
            ->where('user.qnoow_id = { id }')
            ->setParameter('id', (integer)$id)
            ->optionalMatch('(privacy)<-[:OPTION_OF]-(option:PrivacyOption)')
            ->with('privacy, collect(option) AS options')
            ->returns('privacy', 'options')
            ->limit(1);

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if (count($result) < 1) {
            throw new NotFoundHttpException('Privacy not found');
        }

        /* @var $row Row */
        $row = $result->current();

        $privacy = $this->build($row);

        return $privacy;
    }

    /**
     * @param $id
     * @param array $data
     * @return array
     * @throws ValidationException|NotFoundHttpException|MethodNotAllowedHttpException
     */
    public function create($id, array $data)
    {
        $this->validate($data);

        list($userNode, $privacyNode) = $this->getUserAndPrivacyNodesById($id);

        if (!($userNode instanceof Node)) {
            throw new NotFoundHttpException('User not found');
        }

        if ($privacyNode instanceof Node) {
            throw new MethodNotAllowedHttpException(array('PUT'), 'Privacy already exists');
        }

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)')
            ->where('user.qnoow_id = { id }')
            ->setParameter('id', (integer)$id)
            ->merge('(privacy:Privacy)-[:PRIVACY_OF]->(user)')
            ->returns('privacy');

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        /* @var $row Row */
        $row = $result->current();
        $privacyNode = $row->offsetGet('privacy');

        $this->savePrivacyData($privacyNode, $data);

        return $this->getById($id);
    }

    /**
     * @param $id
     * @param array $data
     * @return array
     * @throws ValidationException|NotFoundHttpException
     */
    public function update($id, array $data)
    {

        $this->validate($data);

        list($userNode, $privacyNode) = $this->getUserAndPrivacyNodesById($id);

        if (!($userNode instanceof Node)) {
            throw new NotFoundHttpException('User not found');
        }

        if (!($privacyNode instanceof Node)) {
            throw new NotFoundHttpException('Privacy not found');
        }

        $this->savePrivacyData($privacyNode, $data);

        return $this->getById($id);
    }

    /**
     * @param $id
     */
    public function remove($id)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)<-[:PRIVACY_OF]-(privacy:Privacy)')
            ->where('user.qnoow_id = { id }')
            ->setParameter('id', (integer)$id)
            ->optionalMatch('(privacy)-[r]-()')
            ->delete('privacy', 'r');

        $query = $qb->getQuery();
        $query->getResultSet();
    }

    /**
     * @param array $data
     * @throws ValidationException
     */
    public function validate(array $data)
    {
        $errors = array();
        $metadata = $this->getMetadata();

        foreach ($metadata as $fieldName => $fieldData) {

            $fieldErrors = array();

            if (isset($data[$fieldName])) {

                $fieldValue = $data[$fieldName];

                if (isset($fieldData['type'])) {
                    switch ($fieldData['type']) {
                        case 'choice':
                            $choices = $fieldData['choices'];
                            if (!in_array($fieldValue, array_keys($choices))) {
                                $fieldErrors[] = sprintf('Option with value "%s" is not valid, possible values are "%s"', $fieldValue, implode("', '", array_keys($choices)));
                            }
                            break;
                    }
                }
            } else {

                if (isset($fieldData['required']) && $fieldData['required'] === true) {
                    $fieldErrors[] = 'It\'s required.';
                }
            }

            if (count($fieldErrors) > 0) {
                $errors[$fieldName] = $fieldErrors;
            }
        }

        if (count($errors) > 0) {
            $e = new ValidationException('Validation error');
            $e->setErrors($errors);
            throw $e;
        }
    }

    protected function getChoiceOptions($locale)
    {
        $translationField = 'name_' . $locale;

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(option:PrivacyOption)')
            ->returns("head(filter(x IN labels(option) WHERE x <> 'PrivacyOption')) AS labelName, option.id AS id, option." . $translationField . " AS name")
            ->orderBy('labelName');

        $query = $qb->getQuery();
        $result = $query->getResultSet();

        return $this->buildChoiceOptions($result);
    }

    protected function build(Row $row)
    {
        /* @var $node Node */
        $node = $row->offsetGet('privacy');
        $privacy = $node->getProperties();

        foreach ($row->offsetGet('options') as $option) {
            /* @var $option Node */
            $labels = $option->getLabels();
            foreach ($labels as $label) {
                /* @var $label Label */
                $labelName = $label->getName();
                if ($labelName != 'PrivacyOption') {
                    $typeName = $this->labelToType($labelName);
                    $privacy[$typeName] = $option->getProperty('id');
                }

            }
        }

        return $privacy;
    }

    protected function buildChoiceOptions($result)
    {
        $choiceOptions = array();
        /** @var Row $row */
        foreach ($result as $row) {
            $typeName = $this->labelToType($row->offsetGet('labelName'));
            $optionId = $row->offsetGet('id');
            $optionName = $row->offsetGet('name');

            $choiceOptions[$typeName][$optionId] = $optionName;
        }

        return $choiceOptions;
    }


    protected function getUserAndPrivacyNodesById($id)
    {
        $qb = $this->gm->createQueryBuilder();
        $qb->match('(user:User)')
            ->where('user.qnoow_id = { id }')
            ->setParameter('id', (integer)$id)
            ->optionalMatch('(user)<-[:PRIVACY_OF]-(privacy:Privacy)')
            ->returns('user', 'privacy')
            ->limit(1);

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        if (count($result) < 1) {
            return array(null, null);
        }

        /** @var Row $row */
        $row = $result->current();
        $userNode = $row->offsetGet('user');
        $privacyNode = $row->offsetGet('privacy');

        return array($userNode, $privacyNode);
    }

    protected function savePrivacyData(Node $privacyNode, array $data)
    {
        $metadata = $this->getMetadata();
        $options = $this->getPrivacyNodeOptions($privacyNode);

        foreach ($data as $fieldName => $fieldValue) {

            if (isset($metadata[$fieldName])) {

                $fieldType = $metadata[$fieldName]['type'];
                $editable = isset($metadata[$fieldName]['editable']) ? $metadata[$fieldName]['editable'] === true : true;

                if (!$editable) {
                    continue;
                }

                switch ($fieldType) {
                    case 'choice':
                        if (isset($options[$fieldName])) {
                            $options[$fieldName]->delete();
                        }
                        if (!is_null($fieldValue)) {
                            $optionNode = $this->getPrivacyOptionNode($fieldValue, $fieldName);
                            $optionNode->relateTo($privacyNode, 'OPTION_OF')->save();
                        }
                        break;
                }
            }
        }

        return $privacyNode->save();
    }

    protected function getPrivacyNodeOptions(Node $privacyNode)
    {
        $options = array();
        $optionRelations = $privacyNode->getRelationships('OPTION_OF');

        foreach ($optionRelations as $optionRelation) {

            $optionNode = $optionRelation->getStartNode();
            $optionLabels = $optionNode->getLabels();

            foreach ($optionLabels as $optionLabel) {
                $labelName = $optionLabel->getName();
                if ($labelName != 'PrivacyOption') {
                    $typeName = $this->labelToType($labelName);
                    $options[$typeName] = $optionRelation;
                }
            }
        }

        return $options;
    }

    /**
     * @param $id
     * @param $privacyType
     * @return Node
     */
    protected function getPrivacyOptionNode($id, $privacyType)
    {
        $privacyLabelName = $this->typeToLabel($privacyType);

        $qb = $this->gm->createQueryBuilder();
        $qb->match("(privacyOption:" . $privacyLabelName . ")")
            ->where('privacyOption.id = { id }')
            ->setParameter('id', $id)
            ->returns('privacyOption')
            ->limit(1);

        $query = $qb->getQuery();

        $result = $query->getResultSet();

        /** @var Row $row */
        $row = $result->current();

        return $row->offsetGet('privacyOption');
    }

    protected function getLocale($locale)
    {

        if (!$locale || !in_array($locale, array('en', 'es'))) {
            $locale = $this->defaultLocale;
        }

        return $locale;
    }

    protected function labelToType($labelName)
    {

        return lcfirst(str_replace('PrivacyOption', '', $labelName));
    }

    protected function typeToLabel($typeName)
    {

        return 'PrivacyOption' . ucfirst($typeName);
    }

}