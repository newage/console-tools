#!/bin/bash

_read_migrations() {
    ls -1 ${PWD}/data/migrations/ | sed 's/\(.*\)\..*/\1/'
}

_read_schemas() {
    ls -1 ${PWD}/data/schema/ | sed 's/\(.*\)\..*/\1/'
}

_read_fixtures() {
    ls -1 ${PWD}/data/fixtures/ | sed 's/\(.*\)\..*/\1/'
}

__console_tool() {

    COMPREPLY=()
    cur="${COMP_WORDS[COMP_CWORD]}"
    subcommands_1="migration fixture schema"
    subcommands_schema="clean"
    subcommands_fixture="apply"
    subcommands_migration="create migrate execute last"
    subcommands_migration_execute_params="--up --down"

    if [[ ${COMP_CWORD} == 1 ]] ; then
        COMPREPLY=( $(compgen -W "${subcommands_1}" -- ${cur}) )
        return 0
    fi

    subcmd_1="${COMP_WORDS[1]}"
    case "${subcmd_1}" in
    schema)
        if [[ ${COMP_CWORD} == 2 ]] ; then
            COMPREPLY=( $(compgen -W "${subcommands_schema}" -- ${cur}))
            return 0
        fi

        if [[ ${COMP_CWORD} == 3 ]] ; then
            COMPREPLY=( $(compgen -W "$(_read_schemas)" -- ${cur}))
            return 0
        fi
        return 0
        ;;
    fixture)
        if [[ ${COMP_CWORD} == 2 ]] ; then
            COMPREPLY=( $(compgen -W "${subcommands_fixture}" -- ${cur}))
            return 0
        fi

        if [[ ${COMP_CWORD} == 3 ]] ; then
            COMPREPLY=( $(compgen -W "$(_read_fixtures)" -- ${cur}))
            return 0
        fi
        return 0
        ;;
    migration)
        if [[ ${COMP_CWORD} == 2 ]] ; then
            COMPREPLY=( $(compgen -W "${subcommands_migration}" -- ${cur}))
            return 0
        fi

	subcmd_2="${COMP_WORDS[2]}"
        case "${subcmd_2}" in
        execute)

            if [[ ${COMP_CWORD} == 3 ]] ; then
                COMPREPLY=( $(compgen -W "$(_read_migrations)" -- ${cur}))
                return 0
            fi

            if [[ ${COMP_CWORD} == 4 ]] ; then
                COMPREPLY=( $(compgen -W "${subcommands_migration_execute_params}" -- ${cur}))
                return 0
            fi
            return 0
            ;;

        migrate)
            if [[ ${COMP_CWORD} == 3 ]] ; then
                COMPREPLY=( $(compgen -W "$(_read_migrations)" -- ${cur}))
                return 0
            fi

            return 0
            ;;

        esac
        return 0

        return 0
        ;;
    esac
    return 0
}

complete -F __console_tool zf