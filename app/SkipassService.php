<?php

namespace App;


use Phalski\Skipass\Client;
use Phalski\Skipass\FetchException;
use Phalski\Skipass\NotFoundException;
use Phalski\Skipass\Selector;
use Phalski\Skipass\Skipass;
use Phalski\Skipass\Ticket;
use Phalski\Skipass\Wtp;

class SkipassService implements SkipassServiceInterface
{
    /**
     * @var string
     */
    private $base_uri;
    /**
     * @var string
     */
    private $locale;
    /**
     * @var string
     */
    private $timezone;

    /**
     * @var \DateInterval
     */
    private $max_update_age;

    /**
     * @var Client|null
     */
    private $client;

    /**
     * @var Skipass|null
     */
    private $pass;

    /** @var string|null */
    private $ticket_name;

    /** @var string|null */
    private $wtp_name;


    // local data

    /**
     * @var Project|null
     */
    private $project;

    /**
     * @var \App\Ticket|null
     */
    private $ticket;

    /**
     * @var array
     */
    private $lifts = [];

    /**
     * @var array
     */
    private $logs = [];


    /**
     * SkipassService constructor.
     * @param string $base_uri
     * @param string $locale
     * @param string $timezone
     * @param \DateInterval $max_update_age
     */
    public function __construct(string $base_uri, string $locale, string $timezone, \DateInterval $max_update_age)
    {
        $this->base_uri = $base_uri;
        $this->locale = $locale;
        $this->timezone = $timezone;
        $this->max_update_age = $max_update_age;
    }

    // setup

    public function setProject($project)
    {

        try {
            $this->client = Client::for($project, $this->base_uri, $this->locale);
            $this->project = Project::where('name', $project)->first();
            if (is_null($this->project)) {
                // does a http request, so only call when no project is found in own DB
                $this->client->ensureValidProjectId();
                $this->project = Project::firstOrCreate([
                    'name' => $project
                ]);
            }

            $this->pass = null;
            $this->ticket = null;
            $this->lifts = [];
            $this->logs = [];
        } catch (NotFoundException $e) {
            throw new \InvalidArgumentException('Project name not found: ' . $project, 404, $e);
        } catch (FetchException $e) {
            throw new \RuntimeException('Failed to retrieve data from: ' . $this->base_uri, 502, $e);
        }
    }

    public function setTicket($ticket)
    {
        $this->ticket_name = $ticket;
        $this->ticket = \App\Ticket::where('name', $ticket)->first();
        $this->wtp_name = null;
    }

    public function setWtp($wtp)
    {
        $this->wtp_name = $wtp;
        $this->ticket_name = null;
        $this->ticket = null;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function getTicket(): ?\App\Ticket
    {
        return $this->ticket;
    }

    public function getLifts(): array
    {
        return array_values($this->lifts);
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function updateDayCount()
    {
        $this->ensureSkipass();

        try {
            $count = $this->pass->count();
            if (isset($this->ticket)) {
                $this->ticket->day_count = $count;
                $this->ticket->last_logs_update = new \DateTime();
                $this->ticket->save();
            }
        } catch (FetchException $e) {
            throw new \RuntimeException('Failed to retrieve data from: ' . $this->base_uri, 502, $e);
        }
    }

    public function updateLogs($limit, $offset)
    {
        $this->ensureSkipass();

        try {
            $result = $this->pass->findAll($limit, $offset);
            if ($result->hasErrors()) {
                // handle all the errors withing $result->getErrors()
                throw new \RuntimeException('Received invalid data: ' . $result->getErrors(), 500);
            }
            $data = $result->getData();
            // set the ticket data in case request was done with WTP
            if (is_null($this->ticket)) {
                $this->ticket = \App\Ticket::firstOrCreate(
                    [
                        'project_id' => $this->project->id,
                        'project_name' => $this->project->name,
                        'name' => $data->getTicket()->getId()
                    ], [
                        'project' => $data->getTicket()->getProject(),
                        'pos' => $data->getTicket()->getPos(),
                        'serial' => $data->getTicket()->getSerial()
                    ]
                );
            }


            foreach ($data->getLifts() as $lift) {
                $rideDuration = $lift->getRideDuration();
                $rideDurationString = is_null($rideDuration) ? null : $rideDuration->format('%H:%I:%S.F');
                $this->lifts[$lift->getId()] = Lift::firstOrCreate(
                    [
                        'project_id' => $this->project->id,
                        'name' => $lift->getId()
                    ], [
                        'lower_elevation_meters' => $lift->getLowerElevationMeters(),
                        'upper_elevation_meters' => $lift->getUpperElevationMeters(),
                        'ride_duration' => $rideDurationString
                    ]
                );
            }

            foreach ($data->getDays() as $day) {
                foreach ($day->getRides() as $i => $ride) {
                    array_push($this->logs, Log::firstOrCreate(
                        [
                            'project_id' => $this->project->id,
                            'ticket_id' => $this->ticket->id,
                            'lift_id' => $this->lifts[$ride->getLiftId()]->id,
                            'day_n' => $day->getId(),
                            'ride_n' => $i
                        ], [
                            'logged_at' => $ride->getTimestamp(),
                        ]
                    ));
                }
            }

            if (!empty($this->logs)) {
                $last = end($this->logs);
                $first = reset($this->logs);

                if (is_null($this->ticket->first_lift_id) && $first->day_n == 0 && $first->ride_n == 0) {
                    $this->ticket->first_lift_id = $first->lift_id;
                }

                if (is_null($this->ticket->first_day_at) || $first->logged_at < $this->ticket->first_day_at) {
                    $this->ticket->first_day_at = $first->logged_at;
                }

                if (is_null($this->ticket->first_day_n) || $first->day_n < $this->ticket->first_day_n) {
                    $this->ticket->first_day_n = $first->day_n;
                }

                if (is_null($this->ticket->last_day_at) || $this->ticket->last_day_at < $last->logged_at) {
                    $this->ticket->last_day_at = $last->logged_at;
                }

                if (is_null($this->ticket->last_day_n) || $this->ticket->last_day_n < $last->day_n) {
                    $this->ticket->last_day_n = $last->day_n;
                }
            }
            $this->ticket->last_logs_update = new \DateTime();
            $this->ticket->save();
        } catch (\InvalidArgumentException $e) {
            throw new \OutOfBoundsException('Invalid offset and/or limit value: ' . $offset . '/' . $limit, 400, $e);
        } catch (FetchException $e) {
            throw new \RuntimeException('Failed to retrieve data from: ' . $this->base_uri, 502, $e);
        }
    }

    public function maybeUpdateLogs()
    {
        if (is_null($this->ticket)) {
            // we have no cached ticket or wtp -> always update
            $this->updateLogs(-1, 0);
            // now we have a ticket, so fill day count
            $this->updateDayCount();
        } elseif ( // TODO: correct DateInterval comparison
            is_null($this->ticket->last_day_at) ||
            $this->ticket->last_logs_update->diff($this->ticket->last_day_at)->m < $this->max_update_age->m
        ) {
            // we have a last day set and the difference to the latest update is shorter max age -> update count
            // and fetch only the missing logs
            $this->updateDayCount();

            $offset = is_null($this->ticket->last_day_n) ? 0 : $this->ticket->last_day_n + 1;
            // only update when new days present
            if ($offset == 0 || $offset < $this->ticket->day_count) {
                $this->updateLogs(-1, $offset);
            }
        }
    }


    private function ensureProject()
    {
        if (is_null($this->client)) {
            throw new \LogicException('No project found. Please set the project first', 500);
        }
    }

    /**
     * @throws FetchException
     * @throws NotFoundException
     */
    private function ensureSkipass()
    {
        if (is_null($this->pass)) {
            $this->ensureProject();

            if (isset($this->ticket_name)) {
                $t = Ticket::for($this->ticket_name);
                $this->client->setTicket($t);
                $this->pass = new Skipass($this->client, new Selector($this->timezone));
                if (is_null($this->ticket)) {
                    $this->ticket = \App\Ticket::firstOrCreate(
                        [
                            'project_id' => $this->project->id,
                            'project_name' => $this->project->name,
                            'name' => $t->getId()],
                        [
                            'project' => $t->getProject(),
                            'pos' => $t->getPos(),
                            'serial' => $t->getSerial()
                        ]);
                }
                $this->lifts = [];
                $this->logs = [];
            } elseif (isset($this->wtp_name)) {
                $this->client->setWtp(Wtp::for(wtp_name));
                $this->pass = new Skipass($this->client, new Selector($this->timezone));
                $this->ticket = new \App\Ticket([
                    'project_name' => $this->client->getProjectId()
                ]);
                $this->lifts = [];
                $this->logs = [];
            } else {
                throw new \LogicException('No pass found. Please set via ticket or wtp', 500);
            }
        }
    }

}