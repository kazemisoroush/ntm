<?php

namespace Ntcm\Ntm\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Ntcm\Ntm\Scope\OsScope;

/**
 * @author Soroush Kazemi <kazemi.soroush@gmail.com>
 */
class OsDetected extends Model {

    use OsScope;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name',
        //'address',
        'type',
        'vendor',
        'os_family',
        'os_gen',
        'accuracy',
        'host_id',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return table_name('os_detected');
    }

    /**
     * Create relation to host model.
     *
     * @return BelongsTo
     */
    public function host()
    {
        return $this->belongsTo(Host::class);
    }

    ///**
    // * Mutate the ip address.
    // *
    // * @param $address
    // */
    //public function setAddressAttribute($address)
    //{
    //    $this->attributes['address'] = encode_ip($address);
    //}
    //
    ///**
    // * Mutate the address attribute into ip address.
    // *
    // * @return string
    // */
    //public function getIpAttribute()
    //{
    //    return decode_ip($this->attributes['address']);
    //}

}
