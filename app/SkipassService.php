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
            $this->project = Project::firstOrCreate([
                'name' => $project
            ]);
            $this->pass = null;
            $this->ticket = null;
            $this->lifts = [];
            $this->logs = [];
        } catch (NotFoundException $e) {
            throw new \InvalidArgumentException('Project name is invalid: ' . $project, 400, $e);
        } catch (FetchException $e) {
            throw new \RuntimeException('Failed to retrieve data from: ' . $this->base_uri, 502, $e);
        }
    }

    public function setTicket($ticket)
    {
        $this->ensureProject();

        try {
            $t = Ticket::for($ticket);
            $this->client->setTicket($t);
            $this->pass = new Skipass($this->client, new Selector($this->timezone));
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
            $this->lifts = [];
            $this->logs = [];
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('Ticket has invalid format: ' . $ticket, 400, $e);
        } catch (NotFoundException $e) {
            throw new \InvalidArgumentException('Ticket not found: ' . $ticket, 404, $e);
        } catch (FetchException $e) {
            throw new \RuntimeException('Failed to retrieve data from: ' . $this->base_uri, 502, $e);
        }
    }

    public function setWtp($wtp)
    {
        $this->ensureProject();

        try {
            $this->client->setWtp(Wtp::for($wtp));
            $this->pass = new Skipass($this->client, new Selector($this->timezone));
            $this->ticket = new \App\Ticket([
                'project_name' => $this->client->getProjectId()
            ]);
            $this->lifts = [];
            $this->logs = [];
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('WTP has invalid format: ' . $wtp, 400, $e);
        } catch (NotFoundException $e) {
            throw new \InvalidArgumentException('WTP not found: ' . $wtp, 404, $e);
        } catch (FetchException $e) {
            throw new \RuntimeException('Failed to retrieve data from: ' . $this->base_uri, 502, $e);
        }
    }

    // retrieve data (without ids and fks set)

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
        $this->ensureProject();
        $this->ensureSkipass();

        try {
            $count = $this->pass->count();
            if (!is_null($this->ticket)) {
                $this->ticket->setAttribute('day_count', $count);
                $this->ticket->save();
            }
        } catch (FetchException $e) {
            throw new \RuntimeException('Failed to retrieve data from: ' . $this->base_uri, 502, $e);
        }
    }

    public function updateLogs($limit, $offset)
    {
        $this->ensureProject();
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

                $modified = false;
                if (is_null($this->ticket->first_day_at) || $first->logged_at < $this->ticket->first_day_at) {
                    $this->ticket->first_day_at = $first->logged_at;
                    $modified = true;
                }

                if (is_null($this->ticket->first_day_n) || $first->day_n < $this->ticket->first_day_n) {
                    $this->ticket->first_day_n = $first->day_n;
                    $modified = true;
                }

                if (is_null($this->ticket->last_day_at) || $this->ticket->last_day_at < $last->logged_at) {
                    $this->ticket->last_day_at = $last->logged_at;
                    $modified = true;
                }

                if (is_null($this->ticket->last_day_n) || $this->ticket->last_day_n < $last->day_n) {
                    $this->ticket->last_day_n = $last->day_n;
                    $modified = true;
                }

                if ($modified) {
                    $this->ticket->save();
                }
            }
        } catch (\InvalidArgumentException $e) {
            throw new \OutOfBoundsException('Invalid offset and/or limit value: ' . $offset . '/' . $limit, 400, $e);
        } catch (FetchException $e) {
            throw new \RuntimeException('Failed to retrieve data from: ' . $this->base_uri, 502, $e);
        }
    }

    public function maybeUpdateLogs()
    {
        if (is_null($this->ticket)) {
            // wtp mode -> always fetch all
            $this->updateLogs(-1, 0);
        } elseif (
            is_null($this->ticket->last_day_at) ||
            $this->ticket->updated_at->diff($this->ticket->last_day_at) < $this->max_update_age
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

    private function ensureSkipass()
    {
        if (is_null($this->client)) {
            throw new \LogicException('No pass found. Please set via ticket or wtp', 500);
        }
    }

}