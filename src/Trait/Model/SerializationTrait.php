<?php

namespace AdaiasMagdiel\Rubik\Trait\Model;

trait SerializationTrait
{
    /**
     * Defines how the model will be serialized into JSON.
     *
     * @return array The data to be serialized into JSON.
     */
    public function toArray(): array
    {
        return is_array($this->_data) ? $this->_data : [];
    }

    /**
     * Defines how the model will be serialized into JSON.
     *
     * @return array The data to be serialized into JSON.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
