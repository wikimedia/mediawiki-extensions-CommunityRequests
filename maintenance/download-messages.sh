#!/bin/bash

APIURL="https://meta.wikimedia.org/w/api.php"

## Get all language subpages.
LANG_SUBPAGES="?action=query&format=json&list=allpages&formatversion=2&apprefix=%20Gadget-WishlistIntake%2Fmessages&apnamespace=8"
LANGS=$( curl -s "$APIURL$LANG_SUBPAGES" | jq -r '.query.allpages | map( .title | split( "/" )[2]) | unique | join( " " )' )
for L in $LANGS; do

    ## For each languge, fetch its translations and write them to the i18n directory.
    MSGFILE=$(cd $(dirname $0); pwd)/../i18n/$L.json
    echo $MSGFILE
    curl -s $APIURL'?action=query&format=json&prop=revisions&titles=MediaWiki%3AGadget-WishlistIntake%2Fmessages%2F'$L'&rvprop=content&rvslots=main&formatversion=2&maxage=1800&smaxage=1800' \
        | jq --tab ".query.pages[0].revisions[0].slots.main.content | fromjson | .messages " \
        | sed 's/communitywishlist/communityrequests/g' \
        > $MSGFILE

done
