<?php

namespace Ntcm\Ntm\Scope;

/**
 * @author Soroush Kazemi <kazemi.soroush@gmail.com>
 */
trait PortScope {

    /**
     * Get reserved ticket types query.
     *
     * @param       $query
     * @param array $attributes
     *
     * @return mixed
     */
    public function scopeFindOrCreate($query, $attributes)
    {
        // try to find the host...
        $instance = $query->where('port_id', $attributes['port_id'])
                          ->where('address', encode_ip($attributes['address']))
                          ->first();

        // if the host found return it...
        // otherwise create...
        return $instance ? $instance : $query->create($attributes);
    }

}