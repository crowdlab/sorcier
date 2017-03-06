<?php

namespace REST;

trait Shifter
{
    protected function getShifted($one = false)
    {
        $cid = 0;
        if (isset(static::$schema)) {
            foreach (static::$schema as $k => $v) {
                if (!isset(static::$schema[$k]) || !isset($this->$k)) {
                    continue;
                }
                if ($v == 'int') {
                    $this->$k = (int) $this->$k;
                }
            }
        }
        if (isset(static::$shift)) {
            switch (static::$shift) {
                case 0:
                    $uid = $this->id; // 1st param
                    if (!$one) {
                        $cid = $this->cid;
                    } // 2rd param
                    break;
                case 1:
                    $uid = $this->cid; // 2nd param
                    if (!$one) {
                        $cid = $this->sid;
                    } // 3rd param
                    break;
                case 2:
                    $uid = $this->sid; // 3nd param
                    if (!$one) {
                        $cid = $this->tid;
                    } // 4rd param
                    break;
            }
        } else {
            $uid = $this->id; // 1st param
            if (!$one) {
                $cid = $this->cid;
            } // 2rd param
        }

        return [$uid, $cid];
    }
}
