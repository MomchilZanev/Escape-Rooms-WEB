<?php
require_once(__DIR__ . "/../data/db.php");
require_once(__DIR__ . "/../models/escapeRoom.php");
require_once(__DIR__ . "/./riddleService.php");
require_once(__DIR__ . "/./serializationService.php");

class EscapeRoomService
{
    private $db;
    private $riddleService;
    private $serializationService;

    public function __construct($db = new Database())
    {
        $this->db = $db;
        $this->riddleService = new RiddleService($db);
        $this->serializationService = new SerializationService();
    }

    public function importFromJson($jsonContents)
    {
        $object = $this->serializationService->getObject($jsonContents);
        if ($object == null) {
            echo "Invalid JSON file.";
            return false;
        }

        return $this->importFromObj($object);
    }

    public function importFromObj($object)
    {
        if (!is_array($object)) {
            $object = array($object);
        }

        foreach ($object as $item) {
            $room = new EscapeRoom(
                null,
                $item->name,
                $item->language,
                $item->difficulty,
                $item->timeLimit,
                $item->minPlayers,
                $item->maxPlayers,
                $item->image
            );
            if (!$this->addRoom($room)) {
                return false;
            }

            $riddles = $item->riddles;
            if ($riddles) {
                if (!$this->riddleService->importFromObj($riddles, $room->getId())) {
                    return false;
                }
            }
        }
    }

    public function addRoom($room)
    {
        $result = $this->db->insertRoomQuery(
            $room->difficulty,
            $room->timeLimit,
            $room->minPlayers,
            $room->maxPlayers,
            $room->image,
        );

        if ($result['success']) {
            $room->setId($result['id']);

            $result = $this->db->insertRoomTranslationQuery(
                $room->getId(),
                $room->language,
                $room->name
            );
        }

        return $result['success'];
    }

    public function updateRoom($room)
    {
        if (is_null($room->getId())) {
            return $this->addRoom($room);
        }

        $result = $this->db->updateRoomQuery(
            $room->getId(),
            $room->difficulty,
            $room->timeLimit,
            $room->minPlayers,
            $room->maxPlayers,
            $room->image,
        );

        if ($result['success']) {
            $translation = $this->db->selectRoomTranslationQuery($room->getId(), $room->language)['data'];

            if ($translation) {
                $result = $this->db->updateRoomTranslationQuery(
                    $room->getId(),
                    $room->language,
                    $room->name
                );
            } else {
                $result = $this->db->insertRoomTranslationQuery(
                    $room->getId(),
                    $room->language,
                    $room->name
                );
            }
        }

        return $result['success'];
    }

    public function deleteRoom($roomId)
    {
        if (is_null($roomId)) {
            return false;
        }

        $result = $this->db->deleteRoomQuery($roomId);

        return $result['success'];
    }

    public function getAllRooms($language = null)
    {
        $result = $this->db->selectRoomsQuery();

        if (!$result['success']) {
            return null;
        }

        return $this->dbResultToArrayOfRooms($result, $language);
    }

    public function filterRooms($language = null, $name = null, $minDifficulty = null, $maxDifficulty = null, $minTimeLimit = null, $maxTimeLimit = null, $minPlayers = null, $maxPlayers = null)
    {
        $result = $this->db->selectRoomsWhereQuery($language, $name, $minDifficulty, $maxDifficulty, $minTimeLimit, $maxTimeLimit, $minPlayers, $maxPlayers);

        if (!$result['success']) {
            return null;
        }

        return $this->dbResultToArrayOfRooms($result, $language);
    }

    private function dbResultToArrayOfRooms($dbResult, $language = null)
    {
        $rooms = array();
        foreach ($dbResult['data'] as $record) {
            $translation = null;
            if (!is_null($language)) {
                $translation = $this->db->selectRoomTranslationQuery($record['id'], $language)['data'];
            }

            if (!$translation) {
                $translation = $this->db->selectRoomTranslationsQuery($record['id'])['data'][0];
            }

            $room = new EscapeRoom(
                $record['id'],
                $translation['name'],
                $translation['language'],
                $record['difficulty'],
                $record['timeLimit'],
                $record['minPlayers'],
                $record['maxPlayers'],
                $record['image']
            );
            $room->riddles = $this->riddleService->getAllRiddlesInRoom($room->getId(), $language);

            array_push($rooms, $room);
        }

        return $rooms;
    }
}
?>