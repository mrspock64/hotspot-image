###############################################################################
#
# Generic Logic event handlers — hotspot-image edition
#
# This file replaces RF.Guru's own Logic.tcl entirely. It is a much smaller
# file: it does NOT redefine startup/manual_identification/checkPeriodicIdentify/
# etc. — those come from stock SvxLink's own Logic.tcl and LogicBase.tcl and
# already work correctly. This file only:
#
#   1. Fixes the bug that breaks stock Logic.tcl when used from
#      SimplexLogicType.tcl/RepeaterLogicType.tcl instead of LogicBase.tcl:
#      locale.tcl (spellWord, playTime, playNumber, playFrequency, ...) is
#      never sourced into the per-logic namespace, and namespace path is
#      never extended to the parent, so every locale-dependent proc throws
#      "invalid command name" at runtime. See:
#      https://github.com/Guru-RF/Analog-HotSPOT-SVXLink/issues/2
#
#   2. Re-implements the one genuinely useful RF.Guru hotspot feature (D911#
#      IP readout) as a small, correct addition on top of stock SvxLink,
#      instead of RF.Guru's full custom Logic.tcl replacement (which is what
#      introduced the above bug in the first place, plus a load-time crash
#      from a bad addSecondTickSubscriber call, plus a never-wired-up
#      periodic identify hook).
#
###############################################################################

namespace eval Logic {

# This is the fix: source locale.tcl into this namespace and extend the
# namespace path to the parent (the per-logic namespace, e.g. ::SimplexLogic)
# so that procs mixed in from here can find spellWord/playTime/playNumber/
# playFrequency/etc. Mirrors what LogicBase.tcl does for ReflectorLogic.
sourceTclWithOverrides "locale.tcl"
namespace path [namespace parent]


#
# Executed when a DTMF command has been received. D911# reads out the
# hotspot's current LAN IP address, digit by digit, over the air — handy
# for a headless device whose WiFi network may have changed.
#
# Return 1 to hide the command from further processing, or 0 to let SvxLink
# continue processing as normal (e.g. so undefined commands still fall
# through to the standard "unknown command" handling).
#
proc dtmf_cmd_received {cmd} {
  if {$cmd == "D911"} {
    if {[catch {
      set ip [exec /usr/sbin/mylocalip]
      foreach octet [split $ip "."] {
        spellWord $octet
        playMsg "Default" "decimal"
      }
    } err]} {
      puts "D911 IP readout failed: $err"
      playMsg "Core" "operation_failed"
    }
    return 1
  }

  # D920#: plays a whole, pre-generated audio file rather than stitching
  # together SvxLink's own per-word/per-digit sound library like D911#
  # does above -- SvxLink has no free-text speech of its own, so this is
  # how the dashboard's Radio Test page gets to transmit arbitrary custom
  # content (see dashboard/include/tts_message.php, which generates this
  # file via espeak-ng). Silently does nothing if the file doesn't exist
  # yet -- no custom message has been saved.
  if {$cmd == "D920"} {
    if {[file exists "/etc/svxlink/radiotest_message.wav"]} {
      playFile "/etc/svxlink/radiotest_message.wav"
    }
    return 1
  }

  # D921#: same mechanism, a second independent message slot reserved for
  # automated alerts (e.g. lib/load-monitor/monitor.sh transmitting a
  # spoken warning on sustained high temperature/load) -- not wired up to
  # trigger automatically yet, that's a separate, deliberate decision to
  # make later. For now this just lets the alert message be generated and
  # test-played manually like D920#'s custom message.
  if {$cmd == "D921"} {
    if {[file exists "/etc/svxlink/alert_message.wav"]} {
      playFile "/etc/svxlink/alert_message.wav"
    }
    return 1
  }

  return 0
}


# end of namespace
}

#
# This file has not been truncated
#
