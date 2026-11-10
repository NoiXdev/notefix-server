## [0.0.2](https://github.com/NoiXdev/notefix-server/compare/v0.0.1...v) (2026-11-10)


### Bug Fixes

* **app:** always generate https URLs ([69a26a7](https://github.com/NoiXdev/notefix-server/commit/69a26a72e3ddeee32d80e0529347998fd77a1c18))
* **deploy:** pass Passport keys as base64 stack variables, give the scheduler its own healthcheck ([6c84d3f](https://github.com/NoiXdev/notefix-server/commit/6c84d3f6b5381a4f00abfb95086e39d8a20fe4b3))

## [0.0.1](https://github.com/NoiXdev/notefix-server/compare/985596620ffbeb7524cbb526dc2f3aae5d7f1262...v0.0.1) (2026-11-09)


### Features

* admin-configurable instance settings — mail, S3 storage, async migration ([f15d1bb](https://github.com/NoiXdev/notefix-server/commit/f15d1bbcea0abbe3d20dbe6b7f30203b5f2aa294))
* admin-toggleable self-registration ([5547a60](https://github.com/NoiXdev/notefix-server/commit/5547a60a47773aa066c48eb39b328023e77160e2))
* adopt Laravel Fortify for auth (login/register/2FA backend) ([b9bd18f](https://github.com/NoiXdev/notefix-server/commit/b9bd18f9ac1560432ea55a933fbaf6fef22cfae7))
* branded landing page ([28c34e2](https://github.com/NoiXdev/notefix-server/commit/28c34e248946063c9bc4f9d712255146092672b4))
* Filament admin (users, workspaces, members) ([49b242b](https://github.com/NoiXdev/notefix-server/commit/49b242bb4d8cf1a5bb3570958cf61e67d39f2006))
* first-run setup wizard (create admin + instance name) ([bada7a2](https://github.com/NoiXdev/notefix-server/commit/bada7a2bc29deeb2bf422e9f5c507676fe286be7))
* GET /api/workspaces for the desktop workspace picker ([9a5ac60](https://github.com/NoiXdev/notefix-server/commit/9a5ac6021a74cdc053a002725c9b6a736fde1a57))
* global MFA enforce switch ([2e42f6e](https://github.com/NoiXdev/notefix-server/commit/2e42f6e1a3651aaa67dd8024b4412fe60f8241fa))
* invitations table + model + workspace manage policy (W+) ([d9b7072](https://github.com/NoiXdev/notefix-server/commit/d9b7072926ddd3e6c9340da41d96c189f2fc51d4))
* **invitations:** prune expired invitations daily ([0f0d2a1](https://github.com/NoiXdev/notefix-server/commit/0f0d2a1f705d3b8abdc9309f84e88a5b5647cadc))
* invite accept flow + branded invite pages (W+) ([3a36ea9](https://github.com/NoiXdev/notefix-server/commit/3a36ea9bdf71d4953b172c015df96ee7bf2a78ba))
* login-gated API docs page + minimal web login ([70799d6](https://github.com/NoiXdev/notefix-server/commit/70799d691f6a3b4e266fd8f35109365e1fe9561e))
* notes/folders/revisions tables + per-workspace sync cursor ([285a7a7](https://github.com/NoiXdev/notefix-server/commit/285a7a79278f1625aa41a08c50e9b1a14f08a022))
* oauth discovery endpoint + notefix:// redirect (server) ([705ca51](https://github.com/NoiXdev/notefix-server/commit/705ca51816244bd3c3244d45ab36ebedc0258072))
* **oauth:** accept an RFC 8252 loopback redirect for the desktop client ([4c5bd4f](https://github.com/NoiXdev/notefix-server/commit/4c5bd4f08fb7e29fdfb375033efda4e872446c9e))
* owner sharing UI — members + invite management (W+) ([2a9b742](https://github.com/NoiXdev/notefix-server/commit/2a9b74292768c06b74983782057adb4de65d0ce0))
* passport auth + is_admin on users ([9855966](https://github.com/NoiXdev/notefix-server/commit/985596620ffbeb7524cbb526dc2f3aae5d7f1262))
* sync API endpoints (pull/push) + JSON resources ([a4fafd2](https://github.com/NoiXdev/notefix-server/commit/a4fafd24ef477539b6239b4e556bf352d1c26d63))
* **sync:** carry key generations on notes and the workspace vault generation ([225b2fd](https://github.com/NoiXdev/notefix-server/commit/225b2fd601fc47a9e8baddc6dffec16d2cb9d76a))
* **sync:** millisecond precision for note and folder timestamps ([4b00680](https://github.com/NoiXdev/notefix-server/commit/4b00680141fb72690080de1f74d9df766721294c))
* SyncService pull + monotonic seq allocation ([5367b2c](https://github.com/NoiXdev/notefix-server/commit/5367b2cb84e83748a52d0ded60ebd68207de6deb))
* SyncService push with last-write-wins + revisions ([560b2c7](https://github.com/NoiXdev/notefix-server/commit/560b2c73cfe868594c625938ee018547f926e77f))
* **sync:** the pull carries the caller's role and the owner's open invitations ([5478155](https://github.com/NoiXdev/notefix-server/commit/5478155e41c96805068cc89c4ce32ba48aa059b5))
* TOTP two-factor setup + challenge ([fefbfbb](https://github.com/NoiXdev/notefix-server/commit/fefbfbbe72e660f9c0c90b9db27092c4d21efde2))
* **vault:** attach an invite-wrapped key to an invitation and let the invitee swap in their own ([781d378](https://github.com/NoiXdev/notefix-server/commit/781d378da3a8f0b7097402c78a7965f90bef2cf6))
* **vault:** carry member names on the members listing ([0f81c3a](https://github.com/NoiXdev/notefix-server/commit/0f81c3a79d13335b575c7555164044078f0a9316))
* **vault:** create a workspace vault and replace the caller's wrapped key ([2b0d801](https://github.com/NoiXdev/notefix-server/commit/2b0d80100b2aac140f6544d048b848f0fd0898f4))
* **vault:** every owner may hold their own recovery set ([4c85c89](https://github.com/NoiXdev/notefix-server/commit/4c85c894286656a954fcc8eb55231a98a0596c9c))
* **vault:** per-member wrapped keys, recovery and invite tables; vaultKeys on pull ([20c0d61](https://github.com/NoiXdev/notefix-server/commit/20c0d612b22e939c390e5dbe7fa9e7b664b9dbb0))
* **vault:** resolve an invitation id from its token for the vault invite flow ([e0f416a](https://github.com/NoiXdev/notefix-server/commit/e0f416afc54b6823be51c8aa3bd3de6d12b88e8f))
* **vault:** rotate the vault key on member removal ([1edbd5d](https://github.com/NoiXdev/notefix-server/commit/1edbd5d74b4340b69ac31efbf95f5039151ede20))
* **vault:** rotation codes for members, creator recovery follow-up, members listing ([3f8351d](https://github.com/NoiXdev/notefix-server/commit/3f8351d8d61b157a9daf14baeda0ec160e2bfc9d))
* workspace image storage + manifest/upload/download endpoints (S2b) ([b15cf9e](https://github.com/NoiXdev/notefix-server/commit/b15cf9ee7d3bf07fa6640f9135f77bef1107df38))
* workspace membership policy (viewer read, editor/owner write) ([934ed54](https://github.com/NoiXdev/notefix-server/commit/934ed54a3e3d646438b53161cc3369f3b97777f1))
* workspaces + members model with auto personal workspace ([1083e78](https://github.com/NoiXdev/notefix-server/commit/1083e78ddaf4ede220ee9fd16ea221d62fef3860))
* workspaces hub link + sharing docs (W+) ([a8721cf](https://github.com/NoiXdev/notefix-server/commit/a8721cf5f029b5e14c901a8e2de01f2cbf40c3ad))


### Bug Fixes

* **db:** vault migrations run on MariaDB — short index name, add-before-drop in the recovery index swap ([b3183a1](https://github.com/NoiXdev/notefix-server/commit/b3183a1467f1ce62e58c1fbc929fb6d5a817e106))
* **deps:** update Laravel, Filament, Guzzle, CommonMark and HttpFoundation for security fixes ([b2bb019](https://github.com/NoiXdev/notefix-server/commit/b2bb019e2004dd31a7dcc23a005e918c026f4890))
* register Passport authorization view so /oauth/authorize works ([7720e83](https://github.com/NoiXdev/notefix-server/commit/7720e83186471e282673c76cd5fb8e23fb9e36cc))
* **sync:** hide vault invites when the workspace has no vault, compute the pull role once ([912978d](https://github.com/NoiXdev/notefix-server/commit/912978d6c17644afbf3261de87bd3a8dd6eccb23))
* **sync:** keep an explicit null key generation as null, correct the vault API docs ([4822da3](https://github.com/NoiXdev/notefix-server/commit/4822da3decb9a3a30c6f5478b16d34aeb6ed877f))
* **sync:** never keep plaintext or ciphertext revisions of a protected note ([9fcd2c9](https://github.com/NoiXdev/notefix-server/commit/9fcd2c9a47d843e850fc762c5062c56ca513e645))
* **sync:** persist and echo the protected, title and locked flags ([b6a5bf0](https://github.com/NoiXdev/notefix-server/commit/b6a5bf00638cfad5215ce8a27e7a54222b90f327))
* **sync:** widen notes/folders position to BIGINT ([e6f2564](https://github.com/NoiXdev/notefix-server/commit/e6f2564419d041bd2adb0bcbdcec297fd14e4d7a))
* **vault:** a rotation keeps accepted invitees' wraps; removal deletes the member's recovery rows ([0bf3fc9](https://github.com/NoiXdev/notefix-server/commit/0bf3fc94e672118a4d8a156bdc30c09c68f80d41))
* **vault:** duplicate recovery uploads answer 409 under a race; member removal is atomic ([0523d96](https://github.com/NoiXdev/notefix-server/commit/0523d96451781dc7949e77c2766774ec09d1ab93))
* **vault:** membership rule, absent-means-keep flags, stale wrap cleanup, owner-only vault creation ([e632cd6](https://github.com/NoiXdev/notefix-server/commit/e632cd6992fee1aacdf8352117c5734bc52920af))

