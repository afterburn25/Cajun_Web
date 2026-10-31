# Provider-settings CI port allocation

28 October 2026. Based on integration `0bf2c5d543b77bf333f4315a663f5bab5b45c850`.

Feature [run40675211436](https://github.com/afterburn25/Cajun_Web/actions/runs/40675211436), source `cb916c0729525404ccc30f79902620026e174848`, failed its PHP8.4 lane before checkout: Docker could not bind host port33306 because the address was already in use. The PHP8.2 lane passed. This failure supplies no negative evidence about the application assertions because the failing lane never reached them.

The provider-settings workflow now publishes container port3306 on an available host port and passes the actual Actions service mapping into `CWP_TEST_PORT` for the existing Docker test command. This is the same allocation/connection pattern already used by the passing software domain-cooldown workflow. PHP8.2/8.4, MariaDB10.11, health checks, timeouts, all native assertions and evidence upload remain unchanged. No fixed-port fallback or retry hides service failure.

Local validation parses the workflow and confirms that both unchanged native test commands receive the allocated port, with no remaining fixed33306 reference. Whitespace and repository documentation checks pass. Docker is unavailable in the independent Windows environment; this is configuration validation, not a local Docker pass. Fresh hosted provider-settings jobs remain required, and queued integration workflows should not be duplicated or canceled solely for this correction.
