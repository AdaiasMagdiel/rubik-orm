<?php

namespace AdaiasMagdiel\Rubik\Trait\Model;

trait SerializationTrait
{
    /**
     * Converts the model instance attributes into a plain PHP array.
     *
     * @return array An associative array containing the model's data.
     */
    public function toArray(): array
    {
        return is_array($this->_data) ? $this->_data : [];
    }

    /**
     * Specifies data which should be serialized to JSON.
     * 
     * This method is called automatically when the model is passed to json_encode().
     *
     * @return array The data to be serialized.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
