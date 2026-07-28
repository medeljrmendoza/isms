import { z } from "zod";

export const loginSchema = z.object({
  username: z
    .string()
    .trim()
    .min(1, "Username is required")
    .max(255, "Username is too long"),
  password: z
    .string()
    .min(1, "Password is required")
    .max(255, "Password is too long"),
});

export type LoginFormValues = z.infer<typeof loginSchema>;
