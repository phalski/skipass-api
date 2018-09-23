<?php

namespace App;

interface SkipassServiceInterface
{

    /**
     * @param string $project
     * @throws \InvalidArgumentException if project does not exist
     * @throws \RuntimeException if something went wrong
     */
    public function setProject($project);

    /**
     * @param string $ticket
     * @throws \InvalidArgumentException if ticket format is invalid or pass does not exist
     * @throws \LogicException if no project was set prior this operation
     * @throws \RuntimeException if something went wrong
     */
    public function setTicket($ticket);

    /**
     * @param string $wtp
     * @throws \InvalidArgumentException if wtp format is invalid or pass does not exist
     * @throws \LogicException if no project was set prior this operation
     * @throws \RuntimeException if something went wrong
     */
    public function setWtp($wtp);


    public function getProject(): ?Project;

    public function getTicket(): ?Ticket;

    public function getLifts(): array;

    public function getLogs(): array;

    /**
     * @throws \LogicException if no project and pass were set prior this operation
     * @throws \RuntimeException if something went wrong
     */
    public function updateDayCount();

    /**
     * @param string $limit
     * @param string $offset
     * @throws \OutOfBoundsException if offset is out of bounds
     * @throws \LogicException if no project and pass were set prior this operation
     * @throws \RuntimeException if something went wrong
     */
    public function updateLogs($limit, $offset);

    /**
     * @throws \LogicException if no project and pass were set prior this operation
     * @throws \RuntimeException if something went wrong
     */
    public function maybeUpdateLogs();

}