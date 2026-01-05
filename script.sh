#!/bin/bash
callname=test1; \
curl -s -X GET "https://ext.kara-uas.cz/ocs/v2.php/apps/spreed/api/v4/room" \
     -u "guest:WWGCVt7m.LJMrpND-F4Mhi" \
     -H "OCS-APIRequest: true" \
     -H "Accept: application/json" | jq -r '.ocs.data[] | select(.name == "'$callname'") | "https://nextcloud.kara-uas.cz/call/" + .token'