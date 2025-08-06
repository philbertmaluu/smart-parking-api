<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\BaseController;
use App\Http\Requests\StationRequest;
use App\Models\Station;
use App\Repositories\StationRepository;
use Illuminate\Http\Request;

class StationController extends BaseController
{
    protected $stationRepository;

    public function __construct(StationRepository $stationRepository)
    {
        $this->stationRepository = $stationRepository;
    }

    /**
     * Display a listing of stations
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            if ($search) {
                $stations = $this->stationRepository->searchStations($search, $perPage);
            } else {
                $stations = $this->stationRepository->getAllStationsPaginated($perPage);
            }

            return $this->sendResponse($stations, 'Stations retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving stations', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created station
     */
    public function store(StationRequest $request)
    {
        try {
            $station = $this->stationRepository->createStation($request->validated());

            return $this->sendResponse($station, 'Station created successfully', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error creating station', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified station
     */
    public function show($id)
    {
        try {
            $station = $this->stationRepository->getStationByIdWithRelations($id);

            if (!$station) {
                return $this->sendError('Station not found', [], 404);
            }

            return $this->sendResponse($station, 'Station retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving station', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified station
     */
    public function update(StationRequest $request, $id)
    {
        try {
            $station = $this->stationRepository->updateStation($id, $request->validated());

            if (!$station) {
                return $this->sendError('Station not found', [], 404);
            }

            return $this->sendResponse($station, 'Station updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating station', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified station
     */
    public function destroy($id)
    {
        try {
            $deleted = $this->stationRepository->deleteStation($id);

            if (!$deleted) {
                return $this->sendError('Station not found', [], 404);
            }

            return $this->sendResponse([], 'Station deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting station', $e->getMessage(), 500);
        }
    }

    /**
     * Get active stations for dropdown
     */
    public function getActiveStations()
    {
        try {
            $stations = $this->stationRepository->getActiveStationsForSelect();
            return $this->sendResponse($stations, 'Active stations retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving active stations', $e->getMessage(), 500);
        }
    }

    /**
     * Get station statistics
     */
    public function getStatistics($id, Request $request)
    {
        try {
            $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
            $endDate = $request->get('end_date', now()->toDateString());

            $statistics = $this->stationRepository->getStationStatistics($id, $startDate, $endDate);

            if (empty($statistics)) {
                return $this->sendError('Station not found', [], 404);
            }

            return $this->sendResponse($statistics, 'Station statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving station statistics', $e->getMessage(), 500);
        }
    }
}
