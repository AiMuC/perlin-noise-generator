<?php

namespace MapGenerator;

use InvalidArgumentException;
use LogicException;
use SplFixedArray;

class PerlinNoiseGenerator
{

    /**
     * @var \SplFixedArray[]
     */
    protected $terra;
    protected $persistence;
    protected $size;

    const SIZE = 'size';
    const PERSISTENCE = 'persistence';
    const MAP_SEED = 'map_seed';
    const BASE_SCALE = 32;

    /**
     * @var number|string
     */
    protected $mapSeed;

    /**
     * @var number
     */
    protected $numericMapSeed;

    /**
     * @return number|string
     */
    public function getMapSeed()
    {
        return $this->mapSeed;
    }

    /**
     * @param number|string $mapSeed
     */
    public function setMapSeed($mapSeed)
    {
        if (!is_numeric($mapSeed) && !is_string($mapSeed)) {
            throw new InvalidArgumentException(
                sprintf("mapSeed must be string or numeric, %s given", gettype($mapSeed))
            );
        }

        $this->mapSeed = $mapSeed;

        $this->numericMapSeed = is_numeric($mapSeed)
            ? $mapSeed
            : intval(substr(md5($mapSeed), -8), 16);
    }

    /**
     * @param array $options
     *
     * @return \SplFixedArray[]
     */
    public function generate(array $options = array())
    {
        $this->setOptions($options);
        $this->initTerra();

        for ($k = 0; $k < $this->getOctaves(); $k++) {
            $this->octave($k);
        }

        return $this->terra;
    }

    /**
     * Get noise value at world coordinate (x, y). Supports infinite world.
     *
     * @param float $x World X coordinate
     * @param float $y World Y coordinate
     * @return float Raw noise value (un-normalized sum)
     */
    public function getNoise(float $x, float $y)
    {
        $value = 0.0;
        $amp = 1.0;
        $octave_count = 0;
        $max_octaves = 10;

        while ($amp > 0.0001 && $octave_count < $max_octaves) {
            $spacing = static::BASE_SCALE / pow(2, $octave_count);
            $ix = floor($x / $spacing);
            $iy = floor($y / $spacing);
            $fx = ($x / $spacing) - $ix;
            $fy = ($y / $spacing) - $iy;

            $a = $this->getLatticeValue($ix, $iy, $octave_count);
            $b = $this->getLatticeValue($ix + 1, $iy, $octave_count);
            $c = $this->getLatticeValue($ix, $iy + 1, $octave_count);
            $d = $this->getLatticeValue($ix + 1, $iy + 1, $octave_count);

            $i1 = (1 - $fx) * $a + $fx * $b;
            $i2 = (1 - $fx) * $c + $fx * $d;
            $z = (1 - $fy) * $i1 + $fy * $i2;

            $value += $z * $amp;

            $amp *= $this->persistence;
            $octave_count++;
        }

        return $value;
    }

    /**
     * Get pseudo-random value (0-1) at lattice point for given octave.
     *
     * @param int $ix
     * @param int $iy
     * @param int $octave
     * @return float
     */
    protected function getLatticeValue(int $ix, int $iy, int $octave): float
    {
        $key = $this->mapSeed . ':' . $octave . ':' . $ix . ':' . $iy;
        $hash = hexdec(substr(md5($key), 0, 8));
        mt_srand($hash);
        return mt_rand() / mt_getrandmax();
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options)
    {
        if (array_key_exists(static::MAP_SEED, $options)) {
            $this->setMapSeed($options[static::MAP_SEED]);
        }

        if (array_key_exists(static::SIZE, $options)) {
            $this->setSize($options[static::SIZE]);
        }

        if (array_key_exists(static::PERSISTENCE, $options)) {
            $this->setPersistence($options[static::PERSISTENCE]);
        }
    }

    protected function octave($octave)
    {
        $freq = pow(2, $octave);
        $amp = pow($this->persistence, $octave);

        $n = $m = $freq + 1;

        $arr = array();
        for ($j = 0; $j < $m; $j++) {
            for ($i = 0; $i < $n; $i++) {
                $arr[$j][$i] = $this->random() * $amp;
            }
        }

        $nx = $this->size / ($n - 1);
        $ny = $this->size / ($m - 1);

        for ($ky = 0; $ky < $this->size; $ky++) {
            for ($kx = 0; $kx < $this->size; $kx++) {
                $i = (int)($kx / $nx);
                $j = (int)($ky / $ny);

                $dx0 = $kx - $i * $nx;
                $dx1 = $nx - $dx0;
                $dy0 = $ky - $j * $ny;
                $dy1 = $ny - $dy0;

                $z = ($arr[$j][$i] * $dx1 * $dy1
                        + $arr[$j][$i + 1] * $dx0 * $dy1
                        + $arr[$j + 1][$i] * $dx1 * $dy0
                        + $arr[$j + 1][$i + 1] * $dx0 * $dy0)
                    / ($nx * $ny);

                $this->terra[$ky][$kx] += $z;
            }
        }
    }

    /**
     * terra array initialization
     */
    protected function initTerra()
    {
        if (empty($this->mapSeed)) {
            $this->setMapSeed(microtime(true));
        }

        if (!$this->getPersistence()) {
            throw new LogicException('Persistence must be set');
        }

        if (!$this->getSize()) {
            throw new LogicException('Size must be set');
        }

        // Removed size dependency from seed for better consistency, though generate still uses fixed size
        mt_srand($this->numericMapSeed * $this->persistence);

        $this->terra = new SplFixedArray($this->size);
        for ($y = 0; $y < $this->size; $y++) {
            $this->terra[$y] = new SplFixedArray($this->size);
            for ($x = 0; $x < $this->size; $x++) {
                $this->terra[$y][$x] = 0;
            }
        }
    }

    /**
     * Getting random float from 0 to 1
     *
     * @return float
     */
    protected function random()
    {
        return mt_rand() / mt_getrandmax();
    }

    protected function getOctaves()
    {
        return (int)log($this->size, 2);
    }

    /**
     * @deprecated
     * @return int
     */
    public function getSizes()
    {
        return $this->getSize();
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param int $size
     */
    public function setSize($size)
    {
        if (!is_int($size)) {
            throw new InvalidArgumentException(
                sprintf(
                    "Sizes must be int , %s given", gettype($size)
                )
            );
        }

        $this->size = $size;
    }

    /**
     * @return float
     */
    public function getPersistence()
    {
        return $this->persistence;
    }

    /**
     * @param float $persistence
     */
    public function setPersistence($persistence)
    {
        if (!is_numeric($persistence)) {
            throw new InvalidArgumentException(sprintf("persistence must be numeric, %s given", gettype($persistence)));
        }

        $this->persistence = $persistence;
    }

}
