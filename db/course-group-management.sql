/**
 * Author:  Felix Jacobi
 * Created: 17.07.2019
 * License: https://opensource.org/licenses/MIT MIT license
 */

CREATE TABLE cgr_management_promotion_requests (
  ID      SERIAL      PRIMARY KEY,
  ActGrp  TEXT        NOT NULL UNIQUE
                      REFERENCES groups(Act)
                      ON UPDATE CASCADE
                      ON DELETE CASCADE,
  ActUsr  TEXT        NOT NULL
                      REFERENCES users(Act)
                      ON UPDATE CASCADE
                      ON DELETE CASCADE,
  Created TIMESTAMPTZ NOT NULL,
  Comment TEXT
);

GRANT SELECT, USAGE ON "cgr_management_promotion_requests_id_seq" TO "symfony";
GRANT SELECT, INSERT, UPDATE, DELETE ON "cgr_management_promotion_requests" TO "symfony";