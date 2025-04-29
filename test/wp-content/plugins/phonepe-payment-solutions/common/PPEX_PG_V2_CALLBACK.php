<?php

/**
 * PPEX_PG_V2_CALLBACK
 */
if (!class_exists('PPEX_PG_V2_Callback')) {
	class PPEX_PG_V2_Callback
	{
		private $headers;
		private $payload;
		private string $username;
		private string $password;

		/**
		 * @param string $headers
		 * @param $payload
		 * @param string $username
		 * @param string $password
		 */
		public function __construct($headers, $payload, string $username, string $password)
		{
			$this->headers = $headers;
			$this->payload = $payload;
			$this->username = $username;
			$this->password = $password;
		}

		public function getHeaders()
		{
			return $this->headers;
		}

		/**
		 * @return mixed
		 */
		public function getPayload()
		{
			return $this->payload;
		}

		public function getUsername(): string
		{
			return $this->username;
		}

		public function getPassword(): string
		{
			return $this->password;
		}



	}
}