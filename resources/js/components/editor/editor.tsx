import ReactQuill, { Quill } from "react-quill-new";
import "react-quill-new/dist/quill.snow.css";
import ImageCompress from "quill-image-compress";
import { useMemo } from "react";
import { Controller } from "react-hook-form";

Quill.register("modules/imageCompress", ImageCompress);

interface EditorProps {
    placeholder: string;
    control?: any;
    name: string;
    defaultValue?: string;
}

const Editor = ({
    placeholder,
    control,
    name,
    defaultValue = "",
}: EditorProps) => {
    const modules = useMemo(
        () => ({
            toolbar: [
                [{ header: [1, 2, 3, 4, 5, 6, false] }],

                ["bold", "italic", "underline", "blockquote"],
                [
                    { list: "ordered" },
                    { list: "bullet" },
                    { indent: "-1" },
                    { indent: "+1" },
                ],
                [{ align: "center" }, { align: "right" }, { align: "justify" }],
                ["link", "image"],
            ],
            imageCompress: {
                compress: true,
                quality: 0.8,
                maxWidth: 800,
                maxHeight: 800,
            },
        }),
        [],
    );

    const formats = [
        "header",
        "bold",
        "italic",
        "underline",
        "strike",
        "blockquote",
        "list",
        "indent",
        "link",
        "image",
        "color",
        "align",
    ];

    return (
        <div className="mb-16">
            <Controller
                name={name}
                control={control}
                defaultValue={defaultValue}
                render={({ field: { onChange, value } }) => (
                    <ReactQuill
                        placeholder={placeholder}
                        style={{ height: "200px" }}
                        modules={modules}
                        formats={formats}
                        theme="snow"
                        value={value || ""}
                        onChange={onChange}
                    />
                )}
            />
        </div>
    );
};

export default Editor;
