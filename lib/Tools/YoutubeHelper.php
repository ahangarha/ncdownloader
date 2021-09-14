<?php
namespace OCA\NCDownloader\Tools;

use OCA\NCDownloader\Tools\DBConn;
use OCA\NCDownloader\Tools\Helper;

class YoutubeHelper
{
    public const PROGRESS_PATTERN = '#\[download\]\s+' .
    '(?<percentage>\d+(?:\.\d+)?%)' . //progress
    '\s+of\s+[~]?' .
    '(?<size>\d+(?:\.\d+)?(?:K|M|G)iB)' . //file size
    '(?:\s+at\s+' .
    '(?<speed>(\d+(?:\.\d+)?(?:K|M|G)iB/s)|Unknown speed))' . //speed
    '(?:\s+ETA\s+(?<eta>([\d:]{2,8}|Unknown ETA)))?' . //estimated download time
    '(\s+in\s+(?<totalTime>[\d:]{2,8}))?#i';
    public $file = null;
    public $filesize = null;
    public function __construct()
    {
        $this->dbconn = new DBConn();
        $this->tablename = $this->dbconn->queryBuilder->getTableName("ncdownloader_info");
        $this->user = \OC::$server->getUserSession()->getUser()->getUID();
    }

    public static function create()
    {
        return new static();
    }
    public function getFilePath($output)
    {
        $rules = '#\[download\]\s+Destination:\s+(?<filename>.*\.(?<ext>(mp4|mp3|aac)))$#i';

        preg_match($rules, $output, $matches);

        return $matches['filename'] ?? null;
    }
    public function log($message)
    {
        Helper::debug($message);
    }
    public function updateStatus($status = null)
    {
        if (isset($status)) {
            $this->status = trim($status);
        }
        $sql = sprintf("UPDATE %s set status = ? WHERE gid = ?", $this->tablename);
        $this->dbconn->execute($sql, [$this->status, $this->gid]);
    }
    public function run($buffer, $url)
    {
        $this->gid = Helper::generateGID($url);
        $file = $this->getFilePath($buffer);
        if ($file) {
            $data = [
                'uid' => $this->user,
                'gid' => $this->gid,
                'type' => Helper::DOWNLOADTYPE['YOUTUBE-DL'],
                'filename' => basename($file),
                'status' => Helper::STATUS['ACTIVE'],
                'timestamp' => time(),
                'data' => serialize(['link' => $url]),
            ];
            //save the filename as this runs only once
            $this->file = $file;
            $this->dbconn->insert($data);
            //$this->dbconn->save($data,[],['gid' => $this->gid]);
        }
        if (preg_match_all(self::PROGRESS_PATTERN, $buffer, $matches, PREG_SET_ORDER) !== false) {
            if (count($matches) > 0) {
                $match = reset($matches);

                //save the filesize
                if (!isset($this->filesize) && isset($match['size'])) {
                    $this->filesize = $match['size'];
                }
                $size = $match['size'];
                $percentage = $match['percentage'];
                $speed = $match['speed'] . "|" . $match['eta'];
                $sql = sprintf("UPDATE %s set filesize = ?,speed = ?,progress = ? WHERE gid = ?", $this->tablename);
                $this->dbconn->execute($sql, [$this->filesize, $speed, $percentage, $this->gid]);
                /* $data = [
            'filesize' => $size,
            'speed' => $speed,
            'progress' => $percentage,
            'gid' => $this->gid,
            ];
            $this->dbconn->save([], $data, ['gid' => $this->gid]);*/
            }
        }
    }
}