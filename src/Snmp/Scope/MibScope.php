<?php

namespace Ntcm\Snmp\Scope;

/**
 * @author Soroush Kazemi <kazemi.soroush@gmail.com>
 */
trait MibScope {

    /**
     * Check whether this mib item exists in the database or not.
     *
     * @param        $query
     * @param array  $attributes
     *
     * @return mixed
     */
    public function scopeFindOrCreate($query, $attributes)
    {
        // try to find the host...
        //$instance = $query->where('address', $attributes['address'])->where('oid', $attributes['oid'])->first();
        $instance = $query->where('host_id', $attributes['host_id'])->where('oid', $attributes['oid'])->first();

        if($instance) {
            $query->update($attributes);
        } else {
            $query->create($attributes);
        }

        // if the host found return it...
        // otherwise create...
        return $instance;
    }
}