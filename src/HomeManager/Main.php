<?php
declare(strict_types=1);

namespace HomeManager;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;

class Main extends PluginBase {

    private Config $homes;
    private Config $configData;

    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();

        $this->configData = $this->getConfig();
        $this->homes = new Config($this->getDataFolder() . "homes.yml", Config::YAML);
    }

    private function getMessage(string $key, array $replace = []): string {
        $messages = $this->configData->get("messages", []);
        $msg = $messages[$key] ?? "Message not found.";

        foreach ($replace as $k => $v) {
            $msg = str_replace("{" . $k . "}", (string)$v, $msg);
        }

        return $msg;
    }

    /**
     * Resolve partial player names (API 5 safe)
     */
    private function resolvePlayer(string $partial): ?string {

        $partial = strtolower($partial);

        // Check online players
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if (str_starts_with(strtolower($player->getName()), $partial)) {
                return strtolower($player->getName());
            }
        }

        // Check stored home owners (offline support)
        foreach ($this->homes->getAll() as $playerName => $data) {
            if (str_starts_with(strtolower($playerName), $partial)) {
                return strtolower($playerName);
            }
        }

        return null;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        $cmd = strtolower($command->getName());

        if (!($sender instanceof Player) && !in_array($cmd, ["seehomes", "seehome"])) {
            $sender->sendMessage($this->getMessage("player-only"));
            return true;
        }

        switch ($cmd) {

            case "sethome":

                if (!$sender->hasPermission("home.sethome")) {
                    $sender->sendMessage($this->getMessage("no-permission"));
                    return true;
                }

                if (!isset($args[0])) {
                    $sender->sendMessage($this->getMessage("usage-sethome"));
                    return true;
                }

                $name = strtolower($args[0]);
                $playerName = strtolower($sender->getName());
                $homes = $this->homes->get($playerName, []);

                if (isset($homes[$name])) {
                    $sender->sendMessage($this->getMessage("home-exists", ["home" => $name]));
                    return true;
                }

                if (count($homes) >= $this->configData->get("max-homes", 10)
                    && !$sender->hasPermission("home.bypass")) {
                    $sender->sendMessage($this->getMessage("max-homes"));
                    return true;
                }

                $pos = $sender->getPosition();

                $homes[$name] = [
                    "x" => $pos->getX(),
                    "y" => $pos->getY(),
                    "z" => $pos->getZ(),
                    "world" => $pos->getWorld()->getFolderName()
                ];

                $this->homes->set($playerName, $homes);
                $this->homes->save();

                $sender->sendMessage($this->getMessage("home-set", ["home" => $name]));
                return true;

            case "home":

                if (!isset($args[0])) {
                    $sender->sendMessage($this->getMessage("usage-home"));
                    return true;
                }

                $name = strtolower($args[0]);
                $playerName = strtolower($sender->getName());
                $homes = $this->homes->get($playerName, []);

                if (!isset($homes[$name])) {
                    $sender->sendMessage($this->getMessage("home-not-found", ["home" => $name]));
                    return true;
                }

                $data = $homes[$name];

                $world = Server::getInstance()->getWorldManager()->getWorldByName($data["world"]);
                if ($world === null) {
                    Server::getInstance()->getWorldManager()->loadWorld($data["world"]);
                    $world = Server::getInstance()->getWorldManager()->getWorldByName($data["world"]);
                }

                if ($world === null) {
                    $sender->sendMessage("§cWorld not found.");
                    return true;
                }

                $sender->teleport(new Position(
                    (float)$data["x"],
                    (float)$data["y"],
                    (float)$data["z"],
                    $world
                ));

                $sender->sendMessage($this->getMessage("home-teleported", ["home" => $name]));
                return true;

            case "delhome":

                if (!isset($args[0])) {
                    $sender->sendMessage($this->getMessage("usage-delhome"));
                    return true;
                }

                $name = strtolower($args[0]);
                $playerName = strtolower($sender->getName());
                $homes = $this->homes->get($playerName, []);

                if (!isset($homes[$name])) {
                    $sender->sendMessage($this->getMessage("home-not-found", ["home" => $name]));
                    return true;
                }

                unset($homes[$name]);
                $this->homes->set($playerName, $homes);
                $this->homes->save();

                $sender->sendMessage($this->getMessage("home-deleted", ["home" => $name]));
                return true;

            case "homes":

                $playerName = strtolower($sender->getName());
                $homes = $this->homes->get($playerName, []);
                $list = empty($homes) ? "None" : implode(", ", array_keys($homes));

                $sender->sendMessage($this->getMessage("homes-list", [
                    "homes" => $list
                ]));
                return true;

            case "seehomes":

                if (!isset($args[0])) {
                    $sender->sendMessage($this->getMessage("usage-seehomes"));
                    return true;
                }

                $target = $this->resolvePlayer($args[0]);

                if ($target === null) {
                    $sender->sendMessage($this->getMessage("player-not-found"));
                    return true;
                }

                $homes = $this->homes->get($target, []);
                $list = empty($homes) ? "None" : implode(", ", array_keys($homes));

                $sender->sendMessage($this->getMessage("other-homes-list", [
                    "player" => $target,
                    "homes" => $list
                ]));
                return true;

            case "seehome":

                if (!isset($args[1])) {
                    $sender->sendMessage($this->getMessage("usage-seehome"));
                    return true;
                }

                $target = $this->resolvePlayer($args[0]);

                if ($target === null) {
                    $sender->sendMessage($this->getMessage("player-not-found"));
                    return true;
                }

                $name = strtolower($args[1]);
                $homes = $this->homes->get($target, []);

                if (!isset($homes[$name])) {
                    $sender->sendMessage($this->getMessage("home-not-found", ["home" => $name]));
                    return true;
                }

                $data = $homes[$name];

                $world = Server::getInstance()->getWorldManager()->getWorldByName($data["world"]);
                if ($world === null) {
                    Server::getInstance()->getWorldManager()->loadWorld($data["world"]);
                    $world = Server::getInstance()->getWorldManager()->getWorldByName($data["world"]);
                }

                if ($sender instanceof Player && $world !== null) {
                    $sender->teleport(new Position(
                        (float)$data["x"],
                        (float)$data["y"],
                        (float)$data["z"],
                        $world
                    ));

                    $sender->sendMessage($this->getMessage("home-teleported", ["home" => $name]));
                }

                return true;
        }

        return false;
    }
}
