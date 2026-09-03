<?php

declare(strict_types=1);

namespace VisualDesignerManager\Model;

final class Hierarchy
{
    /** @param list<array<string,mixed>> $nodes */
    public static function assertValid(array $nodes): void
    {
        $byId = [];
        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            if ($id === '' || isset($byId[$id])) {
                throw new \InvalidArgumentException('VDM node IDs must be unique.');
            }
            $byId[$id] = $node;
        }

        foreach ($nodes as $node) {
            $id = (string) $node['id'];
            $type = (string) $node['type'];
            $parentId = $node['parentId'] ?? null;

            if ($parentId === null) {
                if ($type !== NodeSchema::SECTION) {
                    throw new \InvalidArgumentException('Only sections may exist at document root.');
                }
                continue;
            }

            if (!isset($byId[$parentId])) {
                throw new \InvalidArgumentException('VDM parent node does not exist.');
            }

            $parentType = (string) $byId[$parentId]['type'];
            if (!in_array($parentType, [NodeSchema::SECTION, NodeSchema::CONTAINER], true)) {
                throw new \InvalidArgumentException('Only sections and containers may contain nodes.');
            }

            $seen = [$id => true];
            $cursor = $parentId;
            while ($cursor !== null) {
                if (isset($seen[$cursor])) {
                    throw new \InvalidArgumentException('VDM hierarchy contains a cycle.');
                }
                $seen[$cursor] = true;
                $cursor = $byId[$cursor]['parentId'] ?? null;
            }
        }
    }

    private function __construct()
    {
    }
}
