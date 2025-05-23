<?php

class xNAbySyCustomListOf implements ArrayAccess, IteratorAggregate, Countable{
    protected $validType = ''; 
    private ?object $Object = null ;
    private array $list = [];

    public function __construct(...$constructorArgs) {
        $TypeValide = $constructorArgs ;
        $Args=null;
        try {
            if(isset($constructorArgs[0])){
                $TypeValide = $constructorArgs[0] ;
            }
            if(isset($constructorArgs[1])){
                $Args = $constructorArgs[1] ;
            }
            if(isset($TypeValide)){
                $Obj=null;
                if(is_string($TypeValide)){
                    if($TypeValide != ""){
                        $this->validType = $TypeValide;
                    }
                }elseif (is_object($TypeValide)){
                    $Obj = $TypeValide ;
                    $this->validType = get_class($TypeValide);
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Fournit un Tableau de type $TypeN
     * @param mixed $TypeN 
     * @return xNAbySyCustomListOf 
     */
    public static function GetListOf(...$constructorArgs ):xNAbySyCustomListOf{
        $TypeN = $constructorArgs[0] ;
        $ListO=new self($TypeN, $constructorArgs) ;
        return $ListO ;
    }

    public function add(object $nObjet): void {
        if(isset($this->Object)){
            if (!$nObjet instanceof $this->Object) {
                throw new InvalidArgumentException("Impossible d'ajouter au tableau. L'élément doit être de type ".$this->validType);
            }
        }

        $className=get_class($nObjet);
        if($className != $this->validType){
            throw new InvalidArgumentException("Le type ".$className." ne peut s'ajouter au tableau. L'élément doit être de type ".$this->validType);
            exit;
        }
        $this->list[] = $nObjet;
    }

    public function get(int $index): ?object {
        return $this->list[$index] ?? null;
    }

    public function count(): int {
        return count($this->list);
    }

    public function getIterator(): Traversable {
        return new ArrayIterator($this->list);
    }

    // Optionnel : type-sûr en ajoutant fromArray()
    public static function fromArray(array $items): self {
        $collection = new self();
        if(empty($items)) {
            return $collection;
        }
        $FisrtType = get_class($items[0]);
        foreach ($items as $item) {
            if (!get_class($item) == $FisrtType) {
                throw new InvalidArgumentException("Chaque élément doit être de type " . $FisrtType);
            }
            $collection->add($item);
        }
        return $collection;
    }

    public function offsetExists($offset): bool {
        return isset($this->list[$offset]);
    }

    public function offsetGet($offset): object {
        return $this->list[$offset];
    }

    public function offsetSet($offset, $value): void {
        if(isset($this->Object)){
            if (!$value instanceof $this->Object) {
                throw new InvalidArgumentException("Impossible d'ajouter au tableau. L'élément doit être de type ".$this->validType);
            }
        }

        $className=get_class($value);
        if($className != $this->validType){
            throw new InvalidArgumentException("Le type ".$className." ne peut s'ajouter au tableau. L'élément doit être de type ".$this->validType);
            exit;
        }
        
        if ($offset === null) {
            $this->list[] = $value;
        } else {
            $this->list[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void {
        unset($this->list[$offset]);
    }

    public function __debugInfo() {
        return array(
            'ArrayType' => $this->validType,
            'List' => $this->list
        );
    }

}

?>