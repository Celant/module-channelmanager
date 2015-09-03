<?php

/*
	WildPHP - a modular and easily extendable IRC bot written in PHP
	Copyright (C) 2015 WildPHP

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace WildPHP\Modules\ChannelManager;

use WildPHP\BaseModule;
use WildPHP\Validation;

class ChannelManager extends BaseModule
{
	/**
	 * List of channels the bot is currently in.
	 */
	private $channels = [];

	/**
	 * The Auth module's object.
	 *
	 * @var \WildPHP\Modules\Auth\Auth
	 */
	private $auth;

	/**
	 * Set up the module.
	 */
	public function setup()
	{
		// Get the auth module.
		//$this->auth = $this->api->getModule('Auth');
	}

	/**
	 * Register commands.
	 */
	public function registerCommands()
	{
		return [
			'join' => [
				'callback' => 'joinCommand',
				'help'     => 'Joins a channel. Usage: join [channel] [channel] [...]',
				'auth'     => true
			],
			'part' => [
				'callback' => 'partCommand',
				'help'     => 'Leaves a channel. Usage: part [channel] [channel] [...]',
				'auth'     => true
			]
		];
	}

	/**
	 * Register listeners.
	 */
	public function registerListeners()
	{
		return [
			'initialJoin'            => 'irc.data.in.376',
			'channelMessageListener' => 'irc.data.in.privmsg',
			'gateWatcher'            => 'irc.data.in',
			'channelMessageLogger'   => 'irc.data.'
		];
	}

	/**
	 * The Join command.
	 *
	 * @param string $command The current command, for reference.
	 * @param string $params  The command parameters.
	 * @param array  $data    The last data received.
	 */
	public function joinCommand($command, $params, $data)
	{
		if (empty($params))
		{
			$this->api->getIrcConnection()->write($this->api->getGenerator()->ircPrivmsg($data['params']['receivers'], 'Not enough parameters. Usage: join [#channel] [#channel] [...]'));
			return;
		}


		$channels = explode(' ', $params);
		foreach ($channels as $chan)
		{
			if ($this->isInChannel($chan))
			{
				$this->api->getLogger()->info('Not joining channel {channel} because I am already part of it.', ['channel' => $chan]);
				continue;
			}

			$this->channels[] = $chan;
			$this->joinChannel($chan);
		}
	}

	/**
	 * The Part command.
	 *
	 * @param string $command The current command, for reference.
	 * @param string $params  The command parameters.
	 * @param array  $data    The last data received.
	 */
	public function partCommand($command, $params, $data)
	{
		// If no argument specified, attempt to leave the current channel.
		if (empty($params))
			$channels = $data['params']['receivers'];

		else
			$channels = $params;

		$this->api->getIrcConnection()->write($this->api->getGenerator()->ircPart($channels));
	}

	/**
	 * Join a channel.
	 *
	 * @param string|string[] $channel The channel name(s).
	 */
	public function joinChannel($channel)
	{
		if (!is_array($channel))
			$channel = [$channel];

		foreach ($channel as $id => $chan)
		{
			if (empty($chan) || !Validation::isChannel($chan))
				unset($channel[$id]);
		}

		$this->api->getIrcConnection()->write($this->api->getGenerator()->ircJoin(implode(',', $channel)));
	}

	/**
	 * This function handles the initial joining of channels.
	 *
	 * @param array $data
	 */
	public function initialJoin($data)
	{
		$channels = $this->api->getConfigurationStorage()->get('channels');

		foreach ($channels as $chan)
		{
			$this->joinChannel($chan);
		}
	}

	/**
	 * This function handles raising irc.message events.
	 *
	 * @param array $data The last data received.
	 */
	public function channelMessageListener($data)
	{
		// A generic one and a specific one.
		$this->api->getEmitter()->emit('irc.message.channel', [$data['targets'][0], $data]);
		$this->api->getEmitter()->emit('irc.message.channel.' . $data['targets'][0], [$data]);
	}

	/**
	 * This function just logs data for channels.
	 *
	 * @param array $data The last data received.
	 */
	public function channelMessageLogger($data)
	{
		$message = $data['params']['text'];
		$nickname = $data['nick'];
		$channel = $data['targets'][0];

		if (substr($message, 0, 7) == chr(1) . 'ACTION')
			$message = '*' . trim(substr(substr($message, 7), 0, -1)) . '*';

		$this->api->getLogger()->info("({$channel}) <{$nickname}> {$message}");
	}

	/**
	 * This function watches for channel joins and parts, and keeps track of them.
	 *
	 * @param array $data
	 */
	public function gateWatcher($data)
	{
		if (!empty($data['code']) && $data['code'] == 'RPL_TOPIC')
		{
			// Extract the channel for quick access and make a note.
			$channel = $data['params'][1];
			$this->api->getLogger()->info('Joined channel {channel}', ['channel' => $channel]);

			// We want this channel listed so we know we're in.
			$this->addChannel($channel);

			// And fire up an event.
			$this->api->getEmitter()->emit('channel.join', [$channel]);
		}

		if ($data['command'] == 'KICK' || $data['command'] == 'PART')
		{
			$nick = !empty($data['params']['user']) ? $data['params']['user'] : $data['nick'];

			if ($nick != $this->api->getConfigurationStorage()->get('nick'))
				return;

			$channel = !empty($data['params']['channel']) ? $data['params']['channel'] : $data['params']['channels'];

			$this->api->getLogger()->info('Left channel {channel}', ['channel' => $channel]);
			$this->removeChannel($channel);
			$this->api->getEmitter()->emit('channel.part', array($channel));
		}
	}

	/**
	 * Adds a channel to the list.
	 *
	 * @param string $channel
	 */
	public function addChannel($channel)
	{
		if (!in_array($channel, $this->channels))
			$this->channels[] = $channel;
	}

	/**
	 * Removes a channel from the list.
	 *
	 * @param string $channel
	 */
	public function removeChannel($channel)
	{
		if (in_array($channel, $this->channels))
			unset($this->channels[array_search($channel, $this->channels)]);
	}

	/**
	 * Checks if the bot is in a channel.
	 *
	 * @param string $channel
	 *
	 * @return boolean
	 */
	public function isInChannel($channel)
	{
		return in_array($channel, $this->channels);
	}

	/**
	 * List all channels the bot is in
	 *
	 * @return string[]
	 */
	public function listChannels()
	{
		return $this->channels;
	}
}
