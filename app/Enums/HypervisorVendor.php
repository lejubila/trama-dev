<?php

declare(strict_types=1);

namespace App\Enums;

enum HypervisorVendor: string
{
    case VmwareEsxi = 'vmware_esxi';
    case Proxmox = 'proxmox';
    case HyperV = 'hyper_v';
    case XenServer = 'xenserver';
    case XcpNg = 'xcp_ng';
    case Generic = 'generic';

    public function label(): string
    {
        return match ($this) {
            self::VmwareEsxi => 'VMware ESXi',
            self::Proxmox => 'Proxmox VE',
            self::HyperV => 'Microsoft Hyper-V',
            self::XenServer => 'Citrix XenServer',
            self::XcpNg => 'XCP-ng',
            self::Generic => 'Generico',
        };
    }
}
