# Final-payoff fixture compatibility

The unchanged earlier 292-check suite has eight failures because its manual case records three separate receipts with an enabled synthetic gateway but expects exactly the one final-payoff retirement that existed before FIN-02. The new receipt hook attempts retirement immediately after each committed receipt. A timeout can therefore be retried by the next receipt; a lost cancellation response can be resolved by a later terminal readback. Both are meaningful current behaviors. The original report and source are unchanged under adjacent-full01.

The product change adds one condition to the post-receipt branch: a plan that has already become permanently paid off uses the existing payoff retirement function. That function reserves review, retains exact identity, verifies terminal readback, and retains any independent unknown successor obligation. Partial receipt handling remains immediate.

The old final-payoff fixture explicitly pauses synthetic gateway settings during its first two manual receipts, then enables them on the third. This isolates its original single final fault and retains every original assertion. No expected result, count, timeout, or application state is changed by the fixture. The pause is a synthetic configuration boundary, not a real gateway change.

Twelve added real SQL/process checks separately exercise two repeated-receipt scenarios with an enabled synthetic gateway: cancellation timeout and lost cancellation reply. They verify the immediate first attempt, fresh later readback, truthful final payoff/review state, exact three new committed receipts, no additional create, no transport under transaction/mutex, and explicit owner recovery preserving receipts/payoff. Existing first 217 checks and the first-receipt cancellation positive controls remain intact.

Lint passed for all four files. Native execution is pending this bounded adaptation review.
