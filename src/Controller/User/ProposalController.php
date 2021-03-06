<?php

namespace Controller\User;

use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Routing\ClassResourceInterface;
use Model\User\User;
use Nelmio\ApiDocBundle\Annotation\Security;
use Service\MetadataService;
use Service\ProposalService;
use Service\ProposalRecommendatorService;
use Symfony\Component\HttpFoundation\Request;
use Swagger\Annotations as SWG;

class ProposalController extends FOSRestController implements ClassResourceInterface
{
    /**
     * Create a proposal
     *
     * @Post("/proposals")
     * @param User $user
     * @param Request $request
     * @param ProposalService $proposalService
     * @return \FOS\RestBundle\View\View
     * @SWG\Parameter(
     *      name="body",
     *      in="body",
     *      type="json",
     *      schema=@SWG\Schema(
     *          @SWG\Property(property="type", type="string"),
     *          @SWG\Property(property="fields", type="array", @SWG\Items(type = "string")),
     *          example={ "type" = "work", "fields" = { "title" = "my 1st proposal", "description" = "my first proposal", "industry" = "CS", "profession" = "web dev"}},
     *      )
     * )
     * @SWG\Parameter(
     *      name="locale",
     *      in="query",
     *      type="string",
     *      default="es"
     * )
     * @SWG\Response(
     *     response=201,
     *     description="Returns created proposal",
     * )
     * @Security(name="Bearer")
     * @SWG\Tag(name="proposals")
     */
    public function createAction(User $user, Request $request, ProposalService $proposalService)
    {
        $data = $request->request->all();
        $data['locale'] = $request->query->get('locale', 'en');

        $proposal = $proposalService->create($data, $user);

        return $this->view($proposal, 201);
    }

    /**
     * Update a proposal
     *
     * @Put("/proposals/{proposalId}", requirements={"proposalId"="\d+"})
     * @param $proposalId
     * @param Request $request
     * @param User $user
     * @param ProposalService $proposalService
     * @return \FOS\RestBundle\View\View
     * @SWG\Parameter(
     *      name="body",
     *      in="body",
     *      type="json",
     *      schema=@SWG\Schema(
     *          @SWG\Property(property="proposalId", type="string"),
     *          @SWG\Property(property="type", type="string"),
     *          @SWG\Property(property="title", type="string"),
     *          @SWG\Property(property="description", type="string"),
     *          @SWG\Property(property="industry", type="string"),
     *          @SWG\Property(property="profession", type="string"),
     *          @SWG\Property(property="sports", type="string"),
     *          @SWG\Property(property="games", type="string"),
     *          @SWG\Property(property="hobbies", type="string"),
     *          @SWG\Property(property="shows", type="string"),
     *          @SWG\Property(property="restaurants", type="string"),
     *          @SWG\Property(property="plans", type="string"),
     *          @SWG\Property(property="availability", type="array", @SWG\Items(type = "string")),
     *          example={ "proposalId" = "15899079", "name" = "my 1st proposal", "description" = "my first proposal", "industry" = "CS", "profession" = "web dev"},
     *      )
     * )
     * @SWG\Parameter(
     *      name="locale",
     *      in="query",
     *      type="string",
     *      default="es"
     * )
     * @SWG\Response(
     *     response=201,
     *     description="Returns created proposal",
     * )
     * @Security(name="Bearer")
     * @SWG\Tag(name="proposals")
     */
    public function updateAction($proposalId, Request $request, User $user, ProposalService $proposalService)
    {
        $data = $request->request->all();
        $data['locale'] = $request->query->get('locale', 'en');

        $proposal = $proposalService->update($proposalId, $user, $data);

        return $this->view($proposal, 201);
    }

    /**
     * Delete a proposal
     *
     * @Delete("/proposals/{proposalId}", requirements={"proposalId"="\d+"})
     * @param $proposalId
     * @param Request $request
     * @param ProposalService $proposalService
     * @return \FOS\RestBundle\View\View
     * @SWG\Parameter(
     *      name="body",
     *      in="body",
     *      type="json",
     *      schema=@SWG\Schema(
     *          @SWG\Property(property="proposalId", type="string"),
     *          example={ "proposalId" = "15899079"}
     *      )
     * )
     * @SWG\Parameter(
     *      name="locale",
     *      in="query",
     *      type="string",
     *      default="es"
     * )
     * @SWG\Response(
     *     response=201,
     *     description="Returns empty",
     * )
     * @Security(name="Bearer")
     * @SWG\Tag(name="proposals")
     */
    public function deleteAction($proposalId, Request $request, ProposalService $proposalService)
    {
        $data = $request->request->all();
        $data['locale'] = $request->query->get('locale', 'en');

        $proposalService->delete($proposalId, $data);

        return $this->view(array(), 201);
    }

    /**
     * Get all proposals for a user
     *
     * @Get("/proposals")
     * @param Request $request
     * @param User $user
     * @param ProposalService $proposalService
     * @return \FOS\RestBundle\View\View
     *
     * @SWG\Parameter(
     *      name="locale",
     *      in="query",
     *      type="string",
     *      default="es"
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns all proposals"
     * )
     * @Security(name="Bearer")
     * @SWG\Tag(name="proposals")
     */
    public function getUserProposalsAction(Request $request, User $user, ProposalService $proposalService)
    {
        $locale = $request->query->get('locale', 'en');
        $proposals = $proposalService->getByUser($user, $locale);

        return $this->view($proposals, 200);
    }

    /**
     * Get recommendations for all proposals
     *
     * @Get("/proposals/recommendations")
     * @param Request $request
     * @param User $user
     * @param ProposalRecommendatorService $proposalRecommendatorService
     * @return \FOS\RestBundle\View\View
     *
     * @SWG\Parameter(
     *      name="locale",
     *      in="query",
     *      type="string",
     *      default="es"
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns all recommendations",
     * )
     * @Security(name="Bearer")
     * @SWG\Tag(name="proposals")
     */
    public function getRecommendationsAction(User $user, Request $request, ProposalRecommendatorService $proposalRecommendatorService)
    {
        $recommendations = $proposalRecommendatorService->getRecommendations($user, $request);

        return $this->view($recommendations, 200);
    }

    /**
     * Get a proposal
     *
     * @Get("/proposals/{proposalId}", requirements={"proposalId"="\d+"})
     * @param $proposalId
     * @param Request $request
     * @param ProposalService $proposalService
     * @return \FOS\RestBundle\View\View
     * @SWG\Parameter(
     *      name="locale",
     *      in="query",
     *      type="string",
     *      default="es"
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Returns given proposal",
     * )
     * @Security(name="Bearer")
     * @SWG\Tag(name="proposals")
     */
    public function getByIdAction($proposalId, Request $request, ProposalService $proposalService)
    {
        $locale = $request->query->get('locale', 'en');
        $proposal = $proposalService->getById($proposalId, $locale);

        return $this->view($proposal, 200);
    }

    /**
     * Get all proposals for a user
     *
     * @Get("/proposals/metadata")
     * @param Request $request
     * @param MetadataService $metadataService
     * @return \FOS\RestBundle\View\View
     *
     * @SWG\Response(
     *     response=200,
     *     description="Returns proposal metadata"
     * )
     * @Security(name="Bearer")
     * @SWG\Tag(name="proposals")
     */
    public function getMetadataAction(Request $request, MetadataService $metadataService)
    {
        $locale = $request->query->get('locale', 'en');

        $metadata = $metadataService->getProposalMetadata($locale);

        return $this->view($metadata, 200);
    }

    /**
     * Set interested in proposal
     *
     * @Post("/recommendations/proposals")
     * @param User $user
     * @param Request $request
     * @param ProposalService $proposalService
     * @return \FOS\RestBundle\View\View
     * @SWG\Parameter(
     *      name="body",
     *      in="body",
     *      type="json",
     *      schema=@SWG\Schema(
     *          @SWG\Property(property="proposalId", type="string"),
     *          @SWG\Property(property="interested", type="boolean")
     *          )
     * )
     * @SWG\Parameter(
     *      name="locale",
     *      in="query",
     *      type="string",
     *      default="es"
     * )
     * @SWG\Response(
     *     response=201,
     *     description="Returns interested",
     * )
     * @Security(name="Bearer")
     * @SWG\Tag(name="proposals")
     */
    public function setInterestedAction(Request $request, User $user, ProposalService $proposalService)
    {
        $data = $request->request->all();

        $interested = $proposalService->setInterestedInProposal($user, $data);

        return $this->view($interested, 201);
    }

    /**
     * Set candidate accepted
     *
     * @Post("/recommendations/candidates")
     * @param Request $request
     * @param ProposalService $proposalService
     * @return \FOS\RestBundle\View\View
     * @SWG\Parameter(
     *      name="body",
     *      in="body",
     *      type="json",
     *      schema=@SWG\Schema(
     *          @SWG\Property(property="proposalId", type="string"),
     *          @SWG\Property(property="otherUserId", type="string"),
     *          @SWG\Property(property="accepted", type="boolean"),
     *          )
     * )
     * @SWG\Parameter(
     *      name="locale",
     *      in="query",
     *      type="string",
     *      default="es"
     * )
     * @SWG\Response(
     *     response=201,
     *     description="Returns accepted",
     * )
     * @Security(name="Bearer")
     * @SWG\Tag(name="proposals")
     */
    public function setAcceptedAction(Request $request, ProposalService $proposalService)
    {
        $data = $request->request->all();

        $interested = $proposalService->setAcceptedCandidate($data);

        return $this->view($interested, 201);
    }

    /**
     * Skip proposal
     *
     * @Post("/recommendations/proposals/skip")
     * @param Request $request
     * @param User $user
     * @param ProposalService $proposalService
     * @return \FOS\RestBundle\View\View
     * @SWG\Parameter(
     *      name="body",
     *      in="body",
     *      type="json",
     *      schema=@SWG\Schema(
     *          @SWG\Property(property="proposalId", type="string"),
     *          @SWG\Property(property="skipped", type="boolean"),
     *          )
     * )
     * @SWG\Parameter(
     *      name="locale",
     *      in="query",
     *      type="string",
     *      default="es"
     * )
     * @SWG\Response(
     *     response=201,
     *     description="Returns skipped",
     * )
     * @Security(name="Bearer")
     * @SWG\Tag(name="proposals")
     */
    public function setSkippedProposalAction(Request $request, User $user, ProposalService $proposalService)
    {
        $data = $request->request->all();

        $interested = $proposalService->setSkippedProposal($data, $user);

        return $this->view($interested, 201);
    }

    /**
     * Skip candidate
     *
     * @Post("/recommendations/candidates/skip")
     * @param Request $request
     * @param User $user
     * @param ProposalService $proposalService
     * @return \FOS\RestBundle\View\View
     * @SWG\Parameter(
     *      name="body",
     *      in="body",
     *      type="json",
     *      schema=@SWG\Schema(
     *          @SWG\Property(property="proposalId", type="string"),
     *          @SWG\Property(property="skipped", type="boolean"),
     *          )
     * )
     * @SWG\Parameter(
     *      name="locale",
     *      in="query",
     *      type="string",
     *      default="es"
     * )
     * @SWG\Response(
     *     response=201,
     *     description="Returns skipped",
     * )
     * @Security(name="Bearer")
     * @SWG\Tag(name="proposals")
     */
    public function setSkippedCandidateAction(Request $request, ProposalService $proposalService)
    {
        $data = $request->request->all();

        $interested = $proposalService->setSkippedCandidate($data);

        return $this->view($interested, 201);
    }
}