<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $user = $request->__authenticatedUser;
        
        if ($user->user_type == config('constants.ADMIN_ROLE_ID') || $user->user_type == config('constants.SUPERADMIN_ROLE_ID')) {
            return response($this->repository->getAll($request));
        } elseif ($user_id = $request->get('user_id')) {
            return response($this->repository->getUsersJobs($user_id));
        }
        
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->findWithRelations($id, ['translatorJobRel.user']);
        return response($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $response = $this->repository->store($request->__authenticatedUser, $request->all());
        return response($response);
    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $response = $this->repository->updateJob($id, $request->except('_token', 'submit'), $request->__authenticatedUser);
        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $adminSenderEmail = config('app.adminemail');
        $data = $request->all();

        $response = $this->repository->storeJobEmail($data);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        if($user_id = $request->get('user_id')) {

            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return response($response);
        }

        return response()->json(['error' => 'User ID not provided'], 400);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJob($data, $user);

        return response($response);
    }

    public function acceptJobWithId(Request $request)
    {
        $data = $request->get('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJobWithId($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->cancelJobAjax($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob($data);

        return response($response);

    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall($data);

        return response($response);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs($user);

        return response($response);
    }


    public function distanceFeed(Request $request)
    {
        $data = $request->all();

        // Extract data from request or set default values
        $distance = $data['distance'] ?? '';
        $time = $data['time'] ?? '';
        $jobid = $data['jobid'] ?? '';
        $session = $data['session_time'] ?? '';

        // Validate and process flags and comments
        $flagged = $this->processFlagged($data);
        $manually_handled = $this->processManuallyHandled($data);
        $by_admin = $this->processByAdmin($data);
        $admincomment = $data['admincomment'] ?? '';

        // Update Distance if time or distance is provided
        $this->updateDistance($jobid, $distance, $time);

        // Update Job if admincomment, session, flagged, manually_handled, or by_admin is provided
        $this->repository->updateJobDistanceFeed($jobid, $admincomment, $session, $flagged, $manually_handled, $by_admin);

        return response('Record updated!');
    }

    private function processFlagged($data)
    {
        return isset($data['flagged']) && $data['flagged'] === 'true' && $data['admincomment'] !== '' ? 'yes' : 'no';
    }

    private function processManuallyHandled($data)
    {
        return isset($data['manually_handled']) && $data['manually_handled'] === 'true' ? 'yes' : 'no';
    }

    private function processByAdmin($data)
    {
        return isset($data['by_admin']) && $data['by_admin'] === 'true' ? 'yes' : 'no';
    }

    private function updateDistance($jobid, $distance, $time)
    {
        if ($time || $distance) {
            Distance::where('job_id', '=', $jobid)->update(['distance' => $distance, 'time' => $time]);
        }
    }

    public function reopen(Request $request)
    {
        $response = $this->repository->reopen($request->all());

        return response($response);
    }

    public function resendNotifications(Request $request)
    {
        $jobId = $request->get('jobid');
        $job = $this->repository->find($jobId);
        $job_data = $this->repository->jobToData($job);
        
        try {
            $this->repository->sendNotificationTranslator($job, $job_data, '*');
            return response(['success' => 'Push sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }

    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $jobId = $request->get('jobid');
        $job = $this->repository->find($jobId);
        $job_data = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

}
