# Changelog

## 2.0.0

- Require core `^2.0.0` and expose all ten operations through the distributed controller.
- Complete clone and impersonation wiring, validation, duration policy and typed errors.
- Correct nonce interface/expiry semantics with atomic master-DB consumption.
- Add CI3-native provisioning helpers and real framework/database conformance tests.
- Verify official CI3 3.1.13 and Pocketarc 3.3.0 without imposing either as a runtime dependency.

Breaking changes: replace the controller/configuration/routes with the v2 integration,
provide all four adapter factories, install the new nonce schema, and migrate custom
exceptions and nonce stores. Legacy VerifyHmac and Headers aliases are removed.
Core, CI3 and clone-db support PHP >=8.1.
