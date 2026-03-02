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
        $msg = $this->configData->get("messages")[$key] ?? "Message not found.";
        foreach ($replace as $k => $v) {
            $msg = str_replace("{" . $k . "}", $v, $msg);
        }
        return $msg;
    }

    private function resolvePlayer(string $partial): ?string {
        foreach (Server::getInstance()->getOfflinePlayers() as $player) {
            if (stripos($player->getName(), $partial) === 0) {
                return strtolower($player->getName());
            }
        }
        return null;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        $cmd = strtolower($command->getName());

        if (!($sender instanceof Player) && $cmd !== "seehomes" && $cmd !== "seehome") {
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

                if (count($homes) >= $this->configData->get("max-homes") && !$sender->hasPermission("home.bypass")) {
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

                $sender->teleport(new Position($data["x"], $data["y"], $data["z"], $world));
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
                $sender->sendMessage($this->getMessage("homes-list", ["homes" => $list]));
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

                if ($sender instanceof Player) {
                    $sender->teleport(new Position($data["x"], $data["y"], $data["z"], $world));
                    $sender->sendMessage($this->getMessage("home-teleported", ["home" => $name]));
                }
                return true;
        }

        return false;
    }
}
