# Mailbox CI database port allocation

28 October 2026. Based on integration `03c4f606b5e536c7173ebf35200a40715228e527`.

Feature [run40678159136](https://github.com/afterburn25/Cajun_Web/actions/runs/40678159136), source `af377c89e35ffd0e10d02bead4c9d9c3698b5a31`, fails PHP8.4/MySQL8.4 job122781753835 during container initialization, before checkout. Docker cannot bind host port33306 because it is already in use. The other three PHP/database combinations pass. This is an environment collision, not an executed mailbox assertion failure.

The workflow now lets Actions allocate the host side of container port3306 and passes `job.services.database.ports[3306]` into the existing native-test container. This matches the incorporated provider-settings and cooldown workflows. The full PHP8.2/8.4 x MariaDB10.11/MySQL8.4 matrix, health checks, trigger permissions, native commands, audit-failure fixture, artifact upload and concurrency rules are unchanged.

YAML parsing and a structural comparison confirm that only host-port allocation and port propagation change. The candidate workflow SHA256 is `f4c755c80efe45aa5b83745e3cc11a8064ae7932a3df64985f18b9e5b3c10dbf`. Repository documentation and whitespace checks accompany the correction. Docker is unavailable locally; successful hosted execution on the combined candidate remains required. No tests were removed, gateway/provider operations performed, or workflows manually dispatched/canceled. The designated integration writer retains branch ownership.
