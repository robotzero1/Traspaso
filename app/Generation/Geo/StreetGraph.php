<?php

namespace App\Generation\Geo;

use SplPriorityQueue;

/**
 * The walkable street network: nodes with coordinates, undirected edges
 * weighted by length. Computes local edge betweenness: how many shortest
 * walks of up to a given length pass along each street segment, which
 * separates through-streets from side streets (space syntax).
 */
final class StreetGraph
{
    /** @var array<int|string, array{0: float, 1: float}> node id → [lat, lng] */
    private array $nodes = [];

    /** @var array<int|string, array<int|string, float>> node → neighbour → metres */
    private array $adjacency = [];

    public function addNode(int|string $id, float $lat, float $lng): void
    {
        $this->nodes[$id] = [$lat, $lng];
        $this->adjacency[$id] ??= [];
    }

    public function addEdge(int|string $a, int|string $b): void
    {
        if ($a === $b || ! isset($this->nodes[$a], $this->nodes[$b])) {
            return;
        }

        $length = Geo::distanceMetres($this->nodes[$a][0], $this->nodes[$a][1], $this->nodes[$b][0], $this->nodes[$b][1]);
        $this->adjacency[$a][$b] = $length;
        $this->adjacency[$b][$a] = $length;
    }

    /** @return array{0: float, 1: float} */
    public function node(int|string $id): array
    {
        return $this->nodes[$id];
    }

    public function nodeCount(): int
    {
        return count($this->nodes);
    }

    public static function edgeKey(int|string $a, int|string $b): string
    {
        return strcmp((string) $a, (string) $b) < 0 ? "{$a}|{$b}" : "{$b}|{$a}";
    }

    /**
     * Brandes' algorithm, with each source's search cut off at $radius
     * metres. $sampleShare < 1 uses a deterministic sample of sources (every
     * n-th node) and scales up.
     *
     * @param  (callable(int, int): void)|null  $progress  called with (done, total)
     * @return array<string, float> edge key → betweenness
     */
    public function localEdgeBetweenness(float $radiusMetres, float $sampleShare = 1.0, ?callable $progress = null): array
    {
        $betweenness = [];
        $ids = array_keys($this->nodes);
        $step = max(1, (int) round(1 / max(1e-6, min(1.0, $sampleShare))));
        $total = (int) ceil(count($ids) / $step);
        $done = 0;

        for ($i = 0; $i < count($ids); $i += $step) {
            $this->accumulateFrom($ids[$i], $radiusMetres, $betweenness);

            if ($progress !== null && ++$done % 500 === 0) {
                $progress($done, $total);
            }
        }

        if ($step > 1) {
            foreach ($betweenness as $key => $value) {
                $betweenness[$key] = $value * $step;
            }
        }

        return $betweenness;
    }

    /** @param array<string, float> $betweenness */
    private function accumulateFrom(int|string $source, float $radius, array &$betweenness): void
    {
        $distance = [$source => 0.0];
        $sigma = [$source => 1.0];
        $predecessors = [$source => []];
        $order = [];
        $settled = [];

        $queue = new SplPriorityQueue;
        $queue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $queue->insert($source, -0.0);

        while (! $queue->isEmpty()) {
            ['data' => $v, 'priority' => $negative] = $queue->extract();

            if (isset($settled[$v]) || -$negative > $distance[$v] + 1e-9) {
                continue;
            }

            $settled[$v] = true;
            $order[] = $v;

            foreach ($this->adjacency[$v] as $w => $length) {
                $candidate = $distance[$v] + $length;

                if ($candidate > $radius) {
                    continue;
                }

                if (! isset($distance[$w]) || $candidate < $distance[$w] - 1e-9) {
                    $distance[$w] = $candidate;
                    $sigma[$w] = $sigma[$v];
                    $predecessors[$w] = [$v];
                    $queue->insert($w, -$candidate);
                } elseif (abs($candidate - $distance[$w]) <= 1e-9) {
                    $sigma[$w] += $sigma[$v];
                    $predecessors[$w][] = $v;
                }
            }
        }

        $delta = [];

        for ($i = count($order) - 1; $i >= 0; $i--) {
            $w = $order[$i];

            foreach ($predecessors[$w] as $v) {
                $share = $sigma[$v] / $sigma[$w] * (1 + ($delta[$w] ?? 0.0));
                $key = self::edgeKey($v, $w);
                $betweenness[$key] = ($betweenness[$key] ?? 0.0) + $share;
                $delta[$v] = ($delta[$v] ?? 0.0) + $share;
            }
        }
    }
}
