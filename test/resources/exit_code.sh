#!/bin/sh -e

if [ "$#" != "1" ]; then
    echo "Usage: ./exit_code.sh <code>" >&2
    exit 2
fi

echo "Message on STDOUT"
echo "Message on STDERR" >&2
exit $1
