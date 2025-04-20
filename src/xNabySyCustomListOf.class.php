<?php

class xNAbySyCustomListOf implements ArrayAccess, IteratorAggregate, Countable{
    protected $validType = ''; 
    private ?object $Object = null ;
    private array $list = [];

    public function __construct(string $TypeValide=null){
        if(isset($TypeValide)){
            if($TypeValide !=""){
                $this->validType = $TypeValide;
                try {
                    $instance = new $TypeValide();
                    if (!is_object($instance)) {
                        throw new InvalidArgumentException("Le type valide doit être un objet.");
                    }
                    $this->Object = $instance;
                    $this->validType = get_class($instance);
                } catch (\Throwable $th) {
                    //throw $th;
                }
            }
        }
    }

    /**
     * Fournit un Tableau de type $TypeN
     * @param mixed $TypeN 
     * @return xNAbySyCustomListOf 
     */
    public static function GetListOf(object $TypeN ):xNAbySyCustomListOf{
        $TypeName = '' ;
        if (isset($TypeN)){
            $TypeName = get_class($TypeN);
            if ($TypeName == 'xNabySyCustomListOf'){
                
            }
        }
        $ListO=new self($TypeName) ;
        return $ListO ;
    }

    public function add(object $user): void {
        $this->list[] = $user;
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
        if (!$value instanceof $this->Object) {
            throw new InvalidArgumentException("Impossible d'ajouter au tableau. L'élément doit être de type ".$this->validType);
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
}

?>