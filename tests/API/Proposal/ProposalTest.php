<?php

namespace Tests\API\Proposal;

class ProposalTest extends ProposalAPITest
{
    public function testProposal()
    {
//        $this->assertGetOwnEmpty();
        $this->assertCreate();
        $this->assertCreateWithAvailability();
        $this->assertEdit();
        $this->assertDelete();
//        $this->assertGetOwn();
//        $this->assertGetOther();
        $this->assertGetRecommendations();
        $this->assertGetMetadata();
    }

    protected function assertGetOwnEmpty()
    {
        $response = $this->getOwnProposals();
        $formattedResponse = $this->assertJsonResponse($response, 200, $response->getContent());
        $this->assertEquals([], $formattedResponse);
    }

    protected function assertCreate()
    {
        $workProposalData = $this->getWorkProposalData();
        $workResponse = $this->createProposal($workProposalData);
        $formattedResponse = $this->assertJsonResponse($workResponse, 201, $workResponse->getContent());
        $this->assertProposalFormat($formattedResponse);

        $sportProposalData = $this->getSportProposalData();
        $sportResponse = $this->createProposal($sportProposalData);
        $formattedResponse = $this->assertJsonResponse($sportResponse, 201, 'Create sport proposal');
        $this->assertProposalFormat($formattedResponse);

        $videogameProposalData = $this->getVideogameProposalData();
        $videogameResponse = $this->createProposal($videogameProposalData);
        $formattedResponse = $this->assertJsonResponse($videogameResponse, 201, 'Create videogame proposal');
        $this->assertProposalFormat($formattedResponse);

        $hobbyProposalData = $this->getHobbyProposalData();
        $hobbyResponse = $this->createProposal($hobbyProposalData);
        $formattedResponse = $this->assertJsonResponse($hobbyResponse, 201, 'Create hobby proposal');
        $this->assertProposalFormat($formattedResponse);

        $showProposalData = $this->getShowProposalData();
        $showResponse = $this->createProposal($showProposalData);
        $formattedResponse = $this->assertJsonResponse($showResponse, 201, 'Create show proposal');
        $this->assertProposalFormat($formattedResponse);

        $restaurantProposalData = $this->getRestaurantProposalData();
        $restaurantResponse = $this->createProposal($restaurantProposalData);
        $formattedResponse = $this->assertJsonResponse($restaurantResponse, 201, 'Create restaurant proposal');
        $this->assertProposalFormat($formattedResponse);

        $planProposalData = $this->getPlanProposalData();
        $planResponse = $this->createProposal($planProposalData);
        $formattedResponse = $this->assertJsonResponse($planResponse, 201, 'Create plan proposal');
        $this->assertProposalFormat($formattedResponse);
    }

    protected function assertCreateWithAvailability()
    {
        $availabilityProposalData = $this->getFullProposalData();
        $availabilityResponse = $this->createProposal($availabilityProposalData);
        $formattedResponse = $this->assertJsonResponse($availabilityResponse, 201, 'Create availability proposal');
        $this->assertProposalFormat($formattedResponse);
    }

    protected function assertEdit()
    {
        $response = $this->getOwnProposals();
        $formattedResponse = $this->assertJsonResponse($response, 200);
        $workProposals = array_filter(
            $formattedResponse,
            function ($proposal) {
                return $proposal['type'] == 'work';
            }
        );
        $workProposalId = reset($workProposals)['id'];

        $editData = $this->getWorkProposalData2();

        $response = $this->editProposal($workProposalId, $editData);
        $formattedResponse = $this->assertJsonResponse($response, 201, $response->getContent());
        $this->assertProposalFormat($formattedResponse);
    }

    protected function assertDelete()
    {
        $response = $this->getOwnProposals();
        $formattedResponse = $this->assertJsonResponse($response, 200);

        $workProposals = array_filter(
            $formattedResponse,
            function ($proposal) {
                return $proposal['type'] == 'work';
            }
        );
        $proposalId = reset($workProposals)['id'];

        $response = $this->deleteProposal($proposalId);
        $formattedResponse = $this->assertJsonResponse($response, 201);
        $this->assertEquals([], $formattedResponse);
    }

    protected function assertGetOwn()
    {
        $response = $this->getOwnProposals();
        $formattedResponse = $this->assertJsonResponse($response, 200);
        foreach ($formattedResponse as $proposal) {
            $this->assertProposalFormat($proposal);
        }
    }

    protected function assertGetOther()
    {
        $response = $this->getOtherUser('johndoe', 2);
        $formattedResponse = $this->assertJsonResponse($response, 200, $response->getContent());
        foreach ($formattedResponse['proposals'] as $proposal) {
            $this->assertProposalFormat($proposal);
        }
    }

    protected function assertGetRecommendations()
    {
        $workProposalData = $this->getWorkProposalData();
        $this->createProposal($workProposalData, 2);

        $response = $this->getRecommendations(2);
        $formattedResponse = $this->assertJsonResponse($response, 200, $response->getContent());

        $this->assertEquals(10, count($formattedResponse), 'recommendation count');

        $this->assertProposalRecommendationFormat($formattedResponse[1]);
        $this->assertProposalRecommendationFormat($formattedResponse[3]);
        $this->assertProposalRecommendationFormat($formattedResponse[5]);
        $this->assertProposalRecommendationFormat($formattedResponse[7]);
        $this->assertProposalRecommendationFormat($formattedResponse[9]);

        $this->assertUserRecommendationFormat($formattedResponse[0]);
        $this->assertUserRecommendationFormat($formattedResponse[2]);
        $this->assertUserRecommendationFormat($formattedResponse[4]);

    }

    protected function assertGetMetadata()
    {
        $response = $this->getProposalMetadata();
        $formattedResponse = $this->assertJsonResponse($response, 200, "Get Profile metadata");
        $this->assertMetadataFormat($formattedResponse);
    }

    protected function getWorkProposalData()
    {
        return array(
            'type' => 'work',
            'fields' => array(
                'title' => 'work title',
                'description' => 'my work proposal',
                'industry' => array('CS'),
                'profession' => array('web dev')
            )
        );
    }

    protected function getWorkProposalData2()
    {
        return array(
            'type' => 'work',
            'fields' => array(
                'title' => 'work title edited',
                'description' => 'my edited work proposal',
                'industry' => array('Coffee drinking'),
                'profession' => array('web dev')
            )
        );
    }

    protected function getSportProposalData()
    {
        return array(
            'type' => 'sports',
            'fields' => array(
                'title' => 'sport proposal title',
                'description' => 'my sport proposal',
                'sports' => array('football')
            )
        );
    }

    protected function getVideogameProposalData()
    {
        return array(
            'type' => 'games',
            'fields' => array(
                'title' => 'videogame proposal title',
                'description' => 'my videogame proposal',
                'games' => array('GTA')
            )
        );
    }

    protected function getHobbyProposalData()
    {
        return array(
            'type' => 'hobbies',
            'fields' => array(
                'title' => 'hobby proposal title',
                'description' => 'my hobby proposal',
                'hobbies' => array('Painting')
            )
        );
    }

    protected function getShowProposalData()
    {
        return array(
            'type' => 'shows',
            'fields' => array(
                'title' => 'show proposal title',
                'description' => 'my show proposal',
                'show' => array('Theater')
            )
        );
    }

    protected function getRestaurantProposalData()
    {
        return array(
            'type' => 'restaurants',
            'fields' => array(
                'title' => 'restaurant proposal title',
                'description' => 'my restaurant proposal',
                'restaurant' => array('Italian')
            )
        );
    }

    protected function getPlanProposalData()
    {
        return array(
            'type' => 'plans',
            'fields' => array(
                'title' => 'plan proposal title',
                'description' => 'my plan proposal',
                'plan' => array('planning')
            )
        );
    }

    protected function getFullProposalData()
    {
        return array(
            'type' => 'plans',
            'fields' => array(
                'title' => 'plan proposal title',
                'description' => 'my plan proposal',
                'plan' => array('planning'),
                'availability' => array(
                    'dynamic' => array(
                        array(
                            'weekday' => 'friday',
                            'range' => array('Night')
                        ),
                        array(
                            'weekday' => 'saturday',
                            'range' => array('Morning', 'Evening', 'Night')
                        ),
                        array(
                            'weekday' => 'sunday',
                            'range' => array('Morning')
                        ),
                    ),
                    'static' => array(
                        array('days' => array('start' => '2018-01-10', 'end' => '2018-01-10'), 'range' => array('Morning')),
                        array('days' => array('start' => '2018-01-12', 'end' => '2018-01-13'), 'range' => array('Morning', 'Night')),
                    )
                )
            ),
            'participantLimit' => 5,
            'filters' => array(
                'userFilters' => array(
                    'descriptiveGender' => array('man'),
                    'birthday' => array(
                        'max' => 40,
                        'min' => 30,
                    ),
                    'language' => array(
                        array(
                            'tag' => array(
                                'name' => 'English'
                            ),
                            'choices' => array(
                                'full_professional',
                                'professional_working'
                            )
                        )
                    ),
                    'order' => 'similarity DESC'
                )
            )
        );
    }

    protected function assertProposalFormat($proposal)
    {
        $this->assertArrayHasKey('id', $proposal);
        $this->assertArrayHasKey('type', $proposal);
        $this->isType('string')->evaluate($proposal['type']);

        $this->assertArrayHasKey('fields', $proposal);
        $this->assertArrayOfType('array', $proposal['fields'], 'fields is an array of arrays');
        $this->assertArrayHasKey('title', $proposal['fields']);
        $this->assertArrayHasKey('description', $proposal['fields']);
        $this->assertArrayHasKey('photo', $proposal['fields']);
        $this->assertArrayHasKey('participantLimit', $proposal['fields']);
        foreach ($proposal['fields'] as $field) {
            $this->assertArrayHasKey('name', $field);
            $this->assertArrayHasKey('value', $field);
            $this->assertArrayHasKey('type', $field);
            if ($field['type'] === 'choice') {
                $value = $field['value'];
                $this->assertArrayOfType('array', $value, 'choice value is array');
                $this->assertArrayHasKey('value', $value[0]);
                $this->assertArrayHasKey('image', $value[0]);
            }
        }
    }

    protected function assertProposalRecommendationFormat($recommendation)
    {
        $this->assertArrayHasKey('proposal', $recommendation);
        $proposal = $recommendation['proposal'];
        $this->assertProposalFormat($proposal);

        $this->assertArrayHasKey('owner', $recommendation);
        $user = $recommendation['owner'];
        $this->assertUserRecommendationFormat($user);
    }

    protected function assertUserRecommendationFormat($recommendation)
    {
        $this->assertArrayHasKey('id', $recommendation);
        $this->assertArrayHasKey('username', $recommendation);
        $this->assertArrayHasKey('slug', $recommendation);
        $this->assertArrayHasKey('photo', $recommendation);
        $this->isType('array')->evaluate($recommendation['photo']);
        $this->assertArrayHasKey('matching', $recommendation);
        $this->assertArrayHasKey('similarity', $recommendation);
        $this->assertArrayHasKey('age', $recommendation);
        $this->assertArrayHasKey('location', $recommendation);
        $this->isType('array')->evaluate($recommendation['location']);
    }

    protected function assertMetadataFormat($metadata)
    {
        $this->assertArrayOfType('array', $metadata, 'metadata is array of proposals');
        foreach ($metadata as $metadatum) {
            $this->assertArrayOfType('array', $metadatum, 'each proposal is array of fields');
            foreach ($metadatum as $field) {
                $this->isType('array')->evaluate($field);
                $this->assertArrayHasKey('type', $field);
            }
        }
    }
}