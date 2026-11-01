# SMS Gateway

## Day 1 — Laravel and PostgreSQL as the foundation

**Status:** Accepted

**Context:** The gateway needs an API, durable message state, prepaid billing, and maintainable business logic.

**Decision:** Use Laravel with PostgreSQL as the system of record. Keep business use cases in Actions, external integrations behind Services/Contracts, and domain state in models/enums.

**Consequences:** Wallet balances, reservations, transactions, and SMS state are durable and transactionally managed in one primary database.

## Day 2 — Kafka for asynchronous SMS delivery

**Status:** Accepted

**Context:** Sending through an external operator must not block API requests, and Normal traffic should not consume Express capacity.

**Decision:** Publish SMS jobs to separate `normal` and `express` topics, with dedicated consumer groups, worker pools, and `normal-dlq` / `express-dlq` topics. Return `202 Accepted` after the job is queued. Use manual offset commits and commit only after processing succeeds. Key Normal messages by consumer and Express messages by message id. If publishing fails, fail the message, release its funds, and return an error instead of `202`.

**Consequences:** API and delivery throughput can scale separately. Delivery to the operator is at-least-once; duplicates are prevented by an `Idempotency-Key` header, and redelivered messages are settled by their current database status.

## Day 3 — Prepaid wallet reservations and recovery

**Status:** Accepted

**Context:** Concurrent requests and worker redelivery must not overspend a consumer’s balance or charge a message twice.

**Decision:** Reserve funds inside a PostgreSQL transaction while locking the wallet row. Create one reservation per SMS; confirm it and write a debit when the operator accepts, or release/expire it on failure. Make reservation settlement idempotent. Use scheduled retry republishing and stalled-message sweeps. Retry transient operator errors (connection, 429, 5xx) with backoff up to a limit, then dead-letter; reject other errors at once. Reservations expire after a TTL.

**Consequences:** A message is charged at most once, even under concurrent requests or redelivery. The sweep does not expire holds for messages stuck in `processing`.

## Day 4 — ClickHouse for asynchronous event history

**Status:** Accepted

**Context:** Inserting every lifecycle event into an analytics store during SMS sending would add latency and couple message delivery to ClickHouse availability.

**Decision:** Send immutable lifecycle-event records to the Kafka topic `sms-message-events`. A separate writer batches events into ClickHouse table `sms_message_events` (up to 1,000 rows or 2 seconds). Commit writer offsets only after a successful insert. Keep PostgreSQL as the source of truth; event logging must not break sending.

**Consequences:** Event history is queryable without placing ClickHouse on the critical request path. History can lag during an outage.
