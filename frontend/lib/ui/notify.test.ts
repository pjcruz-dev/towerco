import { beforeEach, describe, expect, it } from "vitest";

import { notify } from "@/lib/ui/notify";
import { useNotificationStore } from "@/stores/notification-store";

describe("notify", () => {
  beforeEach(() => {
    useNotificationStore.getState().clear();
  });

  it("pushes a success snack onto the global store", () => {
    notify.success("Saved", "Tower Sites");

    const [item] = useNotificationStore.getState().items;
    expect(item?.level).toBe("success");
    expect(item?.title).toBe("Saved");
    expect(item?.message).toBe("Tower Sites");
  });

  it("accepts a full snack", () => {
    notify({ level: "warning", title: "Access denied", message: "Ask an administrator." });

    expect(useNotificationStore.getState().items[0]).toMatchObject({
      level: "warning",
      title: "Access denied",
      message: "Ask an administrator.",
    });
  });
});
