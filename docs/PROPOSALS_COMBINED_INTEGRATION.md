# Proposals in the combined application

Development integration of proposal source 052c42fee5c2e3eb79ee8fa3fcf0bd9350be3aab onto combined source 3930ad96504006c5adcd511f9061da2fc8dec180. The original feature history is retained as a second parent of the resolved source commit. This is not a new release or production deployment.

The reconciliation adds the administrator proposal navigation, permission mapping, create/revise/send/withdraw handlers, customer proposal list and exact-version decision route. It preserves the newer software-grant domain replacement, licensing/financing routes, seven-section 20i workspace, master portal and backups. Historical proposal-only workflow and obsolete shared-file revisions are not used to overwrite the current application. Existing tests remain; proposal SQL and actual two-tab browser acceptance run separately in addition to all 63 combined native suites per engine.

Staff authorization is re-read under the existing shared mutation lock for creation (including replay), revision, sending and withdrawal. The authenticated identity must match the original actor. Client-support contexts cannot manage or decide proposals and are not offered proposal content in this integration. Proposal responses are private/no-store. Customer approval remains an exact-version commercial scope decision, not a charge or legal signature.

Local checks completed before staging: 321 retained acceptance-harness assertions, 40 actual staff-method/lock-double assertions, 50 decision-service/model assertions, 44 schema-policy assertions, 35 foundation assertions, canonical generation and syntax. These do not replace installed SQL/browser checks on this combined source. The initial canonical-refresh invocation reached the command timeout; its subsequent complete invocation passed. No failed invocation is counted as a success.

The 17 shipping-path before/after identities are recorded in PROPOSALS_COMBINED_SOURCE.json. The prior shipping guard is extended rather than disabled: it verifies this delta, then verifies the earlier combined cloud-focus delta. All other current application files retain their exact bytes. The integration recipe records imports and character-offset edits with whole-file hashes and refuses drift.

No live provider request, payment, customer email, deployment, main update or replacement 1.26.1 archive is performed by these checks. Remaining backlog groups and final whole-site/upgrade acceptance are still required.
